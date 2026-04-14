<?php include '../../includes/auth_check.php';
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';

// Get current term and academic year from system settings
$current_term = getCurrentTerm($conn);
$academic_year = getAcademicYear($conn);
$available_terms = getAvailableTerms($conn);

// Build Academic Year options: distinct values from data + current system year
$year_options = [];
$yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs_rs) {
    while ($yr = $yrs_rs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) {
            $year_options[] = $yr['academic_year'];
        }
    }
    $yrs_rs->close();
}
if (!in_array($academic_year, $year_options, true)) {
    array_unshift($year_options, $academic_year);
}

// Fetch students with their classes
$students = $conn->query("SELECT id, first_name, last_name, class FROM students ORDER BY class, first_name, last_name");

// Fetch fees with their types and amounts
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type, f.description,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GHâ‚µ', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(
                       CASE fa.category 
                           WHEN 'early_years' THEN 'Early Years'
                           WHEN 'primary' THEN 'Primary School'
                       END, ':GHâ‚µ', FORMAT(fa.amount, 2)
                   )
               END
               ORDER BY fa.amount
               SEPARATOR ' | '
           ) as amount_details
    FROM fees f
    LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
    GROUP BY f.id, f.name, f.amount, f.fee_type, f.description
    ORDER BY f.name";
$fees = $conn->query($fees_query);

// Fetch all classes from the classes w-full border-collapse for dropdowns
$classes_result = $conn->query("SELECT name FROM classes ORDER BY id ASC");
$class_options = [];
while ($row = $classes_result->fetch_assoc()) {
    $class_options[] = $row['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Assignment - Salba Montessori Accounting</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="view_fees.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Fees
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-user-tag mr-2"></i>Fee Assignment Center</h1>
                <p class="clean-page-subtitle">
                    <span class="clean-badge clean-badge-primary mr-2">
                        <i class="fas fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($current_term); ?>
                    </span>
                    <span class="clean-badge clean-badge-info">
                        <i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $academic_year)); ?>
                    </span>
                </p>
                <div class="mt-3">
                    <a href="view_assigned_fees.php" class="px-3 py-2 rounded-clean-outline mr-2">
                        <i class="fas fa-list"></i> VIEW ASSIGNMENTS
                    </a>
                    <a href="../reports/student_balances.php" class="px-3 py-2 rounded-clean-success">
                        <i class="fas fa-balance-scale"></i> STUDENT BALANCES
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">

        <!-- Assignment Type Selection -->
        <div class="row justify-center mb-">
            <div class="col-12">
                <div class="clean-bg-white rounded shadow">
                    <div class="clean-bg-white rounded shadow-header">
                        <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-clipboard-list mr-2"></i>Assignment Type</h5>
                        <p class="clean-bg-white rounded shadow-subtitle">Choose how you want to assign the fees</p>
                    </div>
                    <div class="clean-bg-white rounded shadow-body">
                        <div class="flex flex-wrap gap-3">
                            <div class="lg:col-span-4 md:col-span-6">
                                <div class="assignment-type-bg-white rounded shadow clean-select-bg-white rounded shadow" data-type="individual">
                                    <div class="text-center p-4">
                                        <div class="clean-icon-circle clean-icon-primary mb-">
                                            <i class="fas fa-user fa-lg"></i>
                                        </div>
                                        <h6 class="mb- fw-bold">Single Student</h6>
                                        <p class="text-gray-600 mb- small">Assign fees to one specific student</p>
                                        <div class="mt-3">
                                            <span class="clean-badge clean-badge-primary">Individual</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-4 md:col-span-6">
                                <div class="assignment-type-bg-white rounded shadow clean-select-bg-white rounded shadow" data-type="multi-student">
                                    <div class="text-center p-4">
                                        <div class="clean-icon-circle clean-icon-warning mb-">
                                            <i class="fas fa-user-friends fa-lg"></i>
                                        </div>
                                        <h6 class="mb- fw-bold">Multiple Students</h6>
                                        <p class="text-gray-600 mb- small">Select and assign fees to multiple students</p>
                                        <div class="mt-3">
                                            <span class="clean-badge clean-badge-warning">Multi-Select</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-4 md:col-span-6">
                                <div class="assignment-type-bg-white rounded shadow clean-select-bg-white rounded shadow" data-type="class">
                                    <div class="text-center p-4">
                                        <div class="clean-icon-circle clean-icon-success mb-">
                                            <i class="fas fa-users fa-lg"></i>
                                        </div>
                                        <h6 class="mb- fw-bold">Entire Class</h6>
                                        <p class="text-gray-600 mb- small">Assign fees to all students in a class</p>
                                        <div class="mt-3">
                                            <span class="clean-badge clean-badge-success">Bulk Assignment</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form action="assign_fee.php" method="POST" id="assignFeeForm" onsubmit="return handleSubmit(event)">
            <input type="hidden" name="assignment_type" id="assignmentType" value="individual">
            <input type="hidden" name="selectedFeesInput" id="selectedFeesInput">
            
            
            <div class="flex flex-wrap gap-4" id="assignmentContent">
                <!-- Selection Panel -->
                <div class="lg:col-span-6 mb-">
                    <div class="clean-bg-white rounded shadow">
                        <div class="clean-bg-white rounded shadow-header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="clean-bg-white rounded shadow-title mb-" id="selectionTitle"><i class="fas fa-users mr-2"></i>Select Student</h5>
                                    <span class="clean-badge clean-badge-primary" id="selectionBadge">Individual Mode</span>
                                </div>
                            </div>
                        </div>
                        <div class="clean-bg-white rounded shadow-body">
                            <!-- Individual Student Selection -->
                            <div id="individualSelection">
                                <div class="clean-search-box mb-">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="clean-search-input" id="studentSearch" placeholder="Search students by name or class...">
                                </div>

                            <!-- Students List -->
                            <div class="student-list" class="max-h-96 overflow-auto">
                                <?php 
                                $students->data_seek(0); // Reset result pointer
                                $current_class = '';
                                while($student = $students->fetch_assoc()): 
                                    if ($current_class !== $student['class']):
                                        if ($current_class !== '') echo '</div>';
                                        $current_class = $student['class'];
                                        echo '<div class="clean-section-label mt-3 mb-"><i class="fas fa-layer-group mr-2"></i>' . htmlspecialchars($current_class) . '</div>';
                                        echo '<div class="class-group">';
                                    endif;
                                ?>
                                    <div class="clean-student-bg-white rounded shadow mb-" data-student-id="<?php echo $student['id']; ?>" data-student-class="<?php echo htmlspecialchars($student['class']); ?>" data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>">
                                        <div class="clean-selection-checkbox hidden">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="p-3">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                </div>
                                                <span class="clean-badge clean-badge-info"><?php echo htmlspecialchars($student['class']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                <?php if ($current_class !== '') echo '</div>'; ?>
                            </div>

                                <input type="hidden" name="selectedStudentId" id="selectedStudentId">
                                <input type="hidden" name="selectedStudentIds" id="selectedStudentIds">
                                <div id="selectedStudentDisplay" class="clean-info-box clean-info-success hidden mt-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-check fa-2x mr-3"></i>
                                        <div>
                                            <div class="clean-info-label">Selected Student</div>
                                            <div class="clean-info-value" id="selectedStudentName"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi-Student Selection Display -->
                                <div id="selectedStudentsDisplay" class="clean-info-box clean-info-warning hidden mt-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-user-friends fa-2x mr-3"></i>
                                            <div>
                                                <div class="clean-info-label">Selected Students</div>
                                                <span class="clean-badge clean-badge-warning" id="studentCounter">0 Selected</span>
                                            </div>
                                        </div>
                                        <button type="button" class="px-3 py-2 rounded-clean-outline px-3 py-2 rounded-clean-sm" id="clearStudents">
                                            <i class="fas fa-times mr-1"></i>Clear All
                                        </button>
                                    </div>
                                    <div class="selected-students-list mt-3" id="selectedStudentsList">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Class Selection -->
                            <div id="classSelection" class="hidden">
                                <div class="mb-">
                                    <label for="classSelect" class="clean-block text-sm font-medium mb-">
                                        <i class="fas fa-layer-group mr-2"></i>Select Class
                                    </label>
                                    <select class="clean-w-full px-3 py-2 border border-gray-300 rounded" id="classSelect" name="classSelect">
                                        <option value="">Choose a class...</option>
                                        <?php foreach ($class_options as $class_name): ?>
                                            <?php if ($class_name === 'KG 1') { ?>
                                                <option value="KG 1">KG 1</option>
                                            <?php } elseif ($class_name === 'KG 2') { ?>
                                                <option value="KG 2">KG 2</option>
                                            <?php } else { ?>
                                                <option value="<?php echo htmlspecialchars($class_name); ?>"><?php echo htmlspecialchars($class_name); ?></option>
                                            <?php } ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="selectedClassDisplay" class="clean-info-box clean-info-success hidden mt-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-users fa-2x mr-3"></i>
                                        <div>
                                            <div class="clean-info-label">Selected Class</div>
                                            <div class="clean-info-value" id="selectedClassName"></div>
                                            <small class="text-gray-600">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Fees will be assigned to <span class="fw-bold" id="studentCount">0</span> students
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fee Selection -->
                <div class="lg:col-span-6 mb-">
                    <div class="clean-bg-white rounded shadow">
                        <div class="clean-bg-white rounded shadow-header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="clean-bg-white rounded shadow-title mb-"><i class="fas fa-money-bill-wave mr-2"></i>Select Fees</h5>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="clean-badge clean-badge-info" id="feeCounter">0 Selected</span>
                                    <button type="button" class="px-3 py-2 rounded-clean-outline px-3 py-2 rounded-clean-sm" id="clearFees">
                                        <i class="fas fa-times mr-1"></i>Clear All
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="clean-bg-white rounded shadow-body">
                        
                        <!-- Multi-Select Instructions -->
                        <div class="clean-alert clean-alert-info mb-">
                            <i class="fas fa-info-circle"></i>
                            <span><strong>Multi-Selection:</strong> Click on multiple fees to assign them together. Selected fees will be highlighted.</span>
                        </div>
                        
                        <!-- Fee List -->
                        <div class="fee-list" class="max-h-96 overflow-auto">
                                <?php 
                                $fees->data_seek(0); // Reset result pointer
                                while($fee = $fees->fetch_assoc()): 
                                ?>
                                    <div class="clean-fee-bg-white rounded shadow mb-" data-fee-id="<?php echo $fee['id']; ?>" data-fee-type="<?php echo $fee['fee_type']; ?>" data-fee-name="<?php echo htmlspecialchars($fee['name']); ?>">
                                        <div class="clean-selection-checkbox">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="p-4">
                                            <div class="flex justify-between items-start mb-">
                                                <h6 class="mb-"><?php echo htmlspecialchars($fee['name']); ?></h6>
                                                <span class="clean-badge clean-badge-secondary"><?php echo ucfirst(str_replace('_', ' ', $fee['fee_type'])); ?></span>
                                            </div>
                                            
                                            <?php if ($fee['fee_type'] === 'fixed'): ?>
                                                <div class="fee-amount-display">
                                                    GHâ‚µ<?php echo number_format($fee['amount'], 2); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="fee-amount-display">
                                                    <small>Amount varies by <?php echo ($fee['fee_type'] === 'class_based') ? 'class' : 'category'; ?></small>
                                                </div>
                                                <?php if ($fee['amount_details']): ?>
                                                    <small class="text-gray-600 block mt-1">
                                                        <?php echo htmlspecialchars(str_replace(' | ', ', ', $fee['amount_details'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!empty($fee['description'])): ?>
                                                <small class="text-gray-600 block mt-2">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    <?php echo htmlspecialchars($fee['description']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <!-- Selected Fees Summary -->
                            <div id="selectedFeesDisplay" class="hidden mt-4">
                                <div class="clean-summary-box">
                                    <h6 class="mb-">
                                        <i class="fas fa-clipboard-check mr-2"></i>
                                        Selected Fees Summary
                                    </h6>
                                    <div id="selectedFeesList" class="flex flex-wrap gap-2">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="clean-stat-value text-primary" id="totalFeesCount">0</div>
                                                <small class="text-gray-600">Total Fees</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="clean-stat-value text-green-600" id="estimatedTotal">GHâ‚µ0.00</div>
                                                <small class="text-gray-600">Estimated Total</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment Details -->
            <div class="row justify-center mt-4">
                <div class="col-lg-10">
                    <div class="clean-bg-white rounded shadow" id="assignmentDetails">
                        <div class="clean-bg-white rounded shadow-header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="clean-bg-white rounded shadow-title mb-"><i class="fas fa-calendar-alt mr-2"></i>Assignment Details</h5>
                                </div>
                                <span class="clean-badge clean-badge-primary" id="assignmentSummary">Complete form to assign fees</span>
                            </div>
                        </div>
                        <div class="clean-bg-white rounded shadow-body">
                            <div class="flex flex-wrap gap-3">
                                <div class="md:col-span-6">
                                    <label for="due_date" class="clean-block text-sm font-medium mb-">
                                        <i class="fas fa-calendar mr-2"></i>Due Date *
                                    </label>
                                    <input type="date" class="clean-w-full px-3 py-2 border border-gray-300 rounded" id="due_date" name="due_date" required>
                                </div>
                                <div class="md:col-span-6">
                                    <label for="term" class="clean-block text-sm font-medium mb-">
                                        <i class="fas fa-calendar-week mr-2"></i>Academic Term *
                                    </label>
                                    <select class="clean-w-full px-3 py-2 border border-gray-300 rounded" id="term" name="term" required>
                                        <option value="">Select Term...</option>
                                            <?php foreach ($available_terms as $term): ?>
                                                <option value="<?php echo htmlspecialchars($term); ?>" 
                                                    <?php echo $term === $current_term ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($term); ?>
                                                    <?php echo $term === $current_term ? ' (Current)' : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-gray-600 block mt-1">
                                            <i class="fas fa-info-circle mr-1"></i>Current: <?php echo htmlspecialchars($current_term); ?> | Year: <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $academic_year)); ?>
                                        </small>
                                    </div>
                                    <div class="md:col-span-6">
                                        <label for="academic_year" class="clean-block text-sm font-medium mb-">
                                            <i class="fas fa-calendar-alt mr-2"></i>Academic Year *
                                        </label>
                                        <select class="clean-w-full px-3 py-2 border border-gray-300 rounded" id="academic_year" name="academic_year" required>
                                            <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                                <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $academic_year) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="notes" class="clean-block text-sm font-medium mb-">
                                    <i class="fas fa-sticky-note mr-2"></i>Notes
                                </label>
                                <textarea class="clean-w-full px-3 py-2 border border-gray-300 rounded" id="notes" name="notes" rows="3" placeholder="Optional notes about this fee assignment"></textarea>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-4">
                                <div class="flex flex-col flex-md-row gap-3 justify-center items-center">
                                    <button type="reset" class="px-3 py-2 rounded-clean-outline" onclick="resetForm()">
                                        <i class="fas fa-undo mr-2"></i>Reset Form
                                    </button>
                                    <button type="submit" class="px-3 py-2 rounded-clean-success px-3 py-2 rounded-lg" id="submitBtn" disabled>
                                        <i class="fas fa-check-circle mr-2"></i>
                                        <span id="submitBtnText">Assign Selected Fees</span>
                                    </button>
                                </div>
                                
                                <!-- Form Status Indicator -->
                                <div class="text-center mt-3">
                                    <small class="text-gray-600" id="formStatus">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Complete all required fields to enable assignment
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="text-center mt-4 mb-">
                <a href="view_assigned_fees.php" class="px-3 py-2 rounded-clean-outline mr-3">
                    <i class="fas fa-eye mr-2"></i>View Assigned Fees
                </a>
                <a href="../dashboard.php" class="px-3 py-2 rounded-clean-secondary">
                    <i class="fas fa-home mr-2"></i>Back to Dashboard
                </a>
            </div>
        </form>
    </div>

        <script>
        let selectedStudent = null;
        let selectedStudentId = null; // Consistent variable naming
        let selectedStudents = new Set(); // Multi-student selection
        let selectedFees = new Set(); // Multi-fee selection
        let assignmentType = 'individual';
        let selectedClass = null;
        let feeData = {}; // Store fee information
        
        // Assignment type selection
        document.querySelectorAll('.assignment-type-bg-white rounded shadow').forEach(bg-white rounded shadow => {
            bg-white rounded shadow.addEventListener('click', function() {
                document.querySelectorAll('.assignment-type-bg-white rounded shadow').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                
                assignmentType = this.dataset.type;
                document.getElementById('assignmentType').value = assignmentType;
                
                if (assignmentType === 'individual') {
                    document.getElementById('selectionTitle').innerHTML = '<i class="fas fa-users mr-2"></i>Select Student';
                    document.getElementById('selectionBadge').textContent = 'Individual Mode';
                    document.getElementById('selectionBadge').className = 'clean-badge clean-badge-primary';
                    document.getElementById('individualSelection').classList.remove('hidden');
                    document.getElementById('classSelection').classList.add('hidden');
                    document.getElementById('submitBtnText').textContent = 'Assign to Student';
                    // Hide checkboxes, show single selection
                    document.querySelectorAll('.clean-student-bg-white rounded shadow .clean-selection-checkbox').forEach(cb => cb.classList.add('hidden'));
                } else if (assignmentType === 'multi-student') {
                    document.getElementById('selectionTitle').innerHTML = '<i class="fas fa-users mr-2"></i>Select Students';
                    document.getElementById('selectionBadge').textContent = 'Multi-Student Mode';
                    document.getElementById('selectionBadge').className = 'clean-badge clean-badge-warning';
                    document.getElementById('individualSelection').classList.remove('hidden');
                    document.getElementById('classSelection').classList.add('hidden');
                    document.getElementById('submitBtnText').textContent = 'Assign to Selected Students';
                    // Show checkboxes for multi-selection
                    document.querySelectorAll('.clean-student-bg-white rounded shadow .clean-selection-checkbox').forEach(cb => cb.classList.remove('hidden'));
                } else {
                    document.getElementById('selectionTitle').innerHTML = '<i class="fas fa-layer-group mr-2"></i>Select Class';
                    document.getElementById('selectionBadge').textContent = 'Class Mode';
                    document.getElementById('selectionBadge').className = 'clean-badge clean-badge-success';
                    document.getElementById('individualSelection').classList.add('hidden');
                    document.getElementById('classSelection').classList.remove('hidden');
                    document.getElementById('submitBtnText').textContent = 'Assign to Class';
                    // Hide checkboxes
                    document.querySelectorAll('.clean-student-bg-white rounded shadow .clean-selection-checkbox').forEach(cb => cb.classList.add('hidden'));
                }
                
                resetSelections();
                updateAssignmentSummary();
                checkFormComplete();
            });
        });
        
        // Set default assignment type
        document.querySelector('[data-type="individual"]').classList.add('selected');
        
        // Multi-fee selection
        document.querySelectorAll('.clean-fee-bg-white rounded shadow').forEach(bg-white rounded shadow => {
            const feeId = bg-white rounded shadow.dataset.feeId;
            const feeName = bg-white rounded shadow.dataset.feeName;
            const feeType = bg-white rounded shadow.dataset.feeType;
            
            // Store fee data
            feeData[feeId] = {
                id: feeId,
                name: feeName,
                type: feeType
            };
            
            bg-white rounded shadow.addEventListener('click', function() {
                toggleFeeSelection(feeId);
            });
        });
        
        // Clear all fees button
        document.getElementById('clearFees').addEventListener('click', function() {
            clearAllFees();
        });

        // Clear all students button
        document.getElementById('clearStudents').addEventListener('click', function() {
            clearAllStudents();
        });
        
        function toggleFeeSelection(feeId) {
            const bg-white rounded shadow = document.querySelector(`.clean-fee-bg-white rounded shadow[data-fee-id="${feeId}"]`);
            
            if (selectedFees.has(feeId)) {
                // Remove fee
                selectedFees.delete(feeId);
                bg-white rounded shadow.classList.remove('selected');
            } else {
                // Add fee
                selectedFees.add(feeId);
                bg-white rounded shadow.classList.add('selected');
            }
            
            updateFeeCounter();
            updateSelectedFeesDisplay();
            updateSelectedFeesInput();
            checkFormComplete();
        }
        
        function clearAllFees() {
            selectedFees.clear();
            document.querySelectorAll('.clean-fee-bg-white rounded shadow').forEach(bg-white rounded shadow => {
                bg-white rounded shadow.classList.remove('selected');
            });
            updateFeeCounter();
            updateSelectedFeesDisplay();
            updateSelectedFeesInput();
            checkFormComplete();
        }
        
        function updateFeeCounter() {
            const count = selectedFees.size;
            const counter = document.getElementById('feeCounter');
            counter.textContent = `${count} Selected`;
            
            if (count === 0) {
                counter.className = 'selection-counter';
            } else if (count === 1) {
                counter.className = 'selection-counter';
                counter.style.background = 'linear-gradient(135deg, #17a2b8, #138496)';
            } else {
                counter.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
            }
        }
        
        function updateSelectedFeesDisplay() {
            const display = document.getElementById('selectedFeesDisplay');
            const list = document.getElementById('selectedFeesList');
            const totalCount = document.getElementById('totalFeesCount');
            
            if (selectedFees.size === 0) {
                display.classList.add('hidden');
                return;
            }
            
            display.classList.remove('hidden');
            list.innerHTML = '';
            
            selectedFees.forEach(feeId => {
                const fee = feeData[feeId];
                const feeItem = document.createElement('div');
                feeItem.className = 'md:col-span-6 lg:col-span-4';
                feeItem.innerHTML = `
                    <div class="bg-white rounded shadow border-success bg-light">
                        <div class="bg-white rounded shadow-body p-2">
                            <div class="flex justify-between items-center">
                                <small class="fw-bold">${fee.name}</small>
                                <button type="button" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-danger" onclick="toggleFeeSelection('${feeId}')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <small class="text-gray-600">${fee.type.replace('_', ' ').toUpperCase()}</small>
                        </div>
                    </div>
                `;
                list.appendChild(feeItem);
            });
            
            totalCount.textContent = selectedFees.size;
        }
        
        function updateSelectedFeesInput() {
            const input = document.getElementById('selectedFeesInput');
            input.value = Array.from(selectedFees).join(',');
        }

        // Multi-student selection functions
        function toggleStudentSelection(studentId) {
            const studentbg-white rounded shadow = document.querySelector(`.clean-student-bg-white rounded shadow[data-student-id="${studentId}"]`);
            
            if (selectedStudents.has(studentId)) {
                // Deselect student
                selectedStudents.delete(studentId);
                studentbg-white rounded shadow.classList.remove('selected');
            } else {
                // Select student
                selectedStudents.add(studentId);
                studentbg-white rounded shadow.classList.add('selected');
            }
            
            updateStudentCounter();
            updateSelectedStudentsDisplay();
            updateSelectedStudentsInput();
        }

        function clearAllStudents() {
            selectedStudents.clear();
            document.querySelectorAll('.clean-student-bg-white rounded shadow').forEach(bg-white rounded shadow => {
                bg-white rounded shadow.classList.remove('selected');
            });
            updateStudentCounter();
            updateSelectedStudentsDisplay();
            updateSelectedStudentsInput();
        }

        function updateStudentCounter() {
            const counter = document.getElementById('studentCounter');
            const count = selectedStudents.size;
            counter.textContent = `${count} Student${count !== 1 ? 's' : ''} Selected`;
            
            // Show/hide the display area
            if (count > 0) {
                document.getElementById('selectedStudentsDisplay').classList.remove('hidden');
                document.getElementById('selectedStudentDisplay').classList.add('hidden');
            } else {
                document.getElementById('selectedStudentsDisplay').classList.add('hidden');
            }
        }

        function updateSelectedStudentsDisplay() {
            const max-w-7xl mx-auto = document.getElementById('selectedStudentsList');
            max-w-7xl mx-auto.innerHTML = '';
            
            selectedStudents.forEach(studentId => {
                const studentbg-white rounded shadow = document.querySelector(`.clean-student-bg-white rounded shadow[data-student-id="${studentId}"]`);
                const studentName = studentbg-white rounded shadow.dataset.studentName;
                const studentClass = studentbg-white rounded shadow.dataset.studentClass;
                
                const item = document.createElement('div');
                item.className = 'selected-student-item';
                item.innerHTML = `
                    <div>
                        <strong>${studentName}</strong>
                        <small class="text-gray-600 block">${studentClass}</small>
                    </div>
                    <button type="button" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-danger" onclick="toggleStudentSelection('${studentId}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                max-w-7xl mx-auto.appendChild(item);
            });
        }

        function updateSelectedStudentsInput() {
            const input = document.getElementById('selectedStudentIds');
            input.value = Array.from(selectedStudents).join(',');
        }
        
        // Update fee amounts based on selected student's class
        function updateFeeAmounts() {
            // This function can be used to update fee displays based on student selection
            // For now, fees are displayed with their base amounts or class-specific info
            updateAssignmentSummary();
        }

        function updateAssignmentSummary() {
            const summary = document.getElementById('assignmentSummary');
            let studentCount = 0;
            
            if (assignmentType === 'individual') {
                studentCount = selectedStudent ? 1 : 0;
            } else if (assignmentType === 'multi-student') {
                studentCount = selectedStudents.size;
            } else if (assignmentType === 'class') {
                studentCount = selectedClass ? parseInt(document.getElementById('studentCount')?.textContent) || 0 : 0;
            }
            
            const feeCount = selectedFees.size;
            
            if (feeCount > 0 && studentCount > 0) {
                const totalAssignments = studentCount * feeCount;
                summary.textContent = `${totalAssignments} Assignment${totalAssignments !== 1 ? 's' : ''} Ready`;
                summary.className = 'badge bg-success text-white';
            } else if (feeCount > 0) {
                summary.textContent = `${feeCount} Fee${feeCount !== 1 ? 's' : ''} Selected - Choose Target`;
                summary.className = 'badge bg-info text-white';
            } else if (studentCount > 0) {
                summary.textContent = `${studentCount} Student${studentCount !== 1 ? 's' : ''} Selected - Choose Fees`;
                summary.className = 'badge bg-warning text-dark';
            } else {
                summary.textContent = 'Select Target & Fees';
                summary.className = 'badge bg-secondary text-white';
            }
            
            // Ensure assignment details are visible
            const assignmentDetails = document.getElementById('assignmentDetails');
            if (assignmentDetails) {
                assignmentDetails.style.display = 'block';
            }
        }
        
        // Class selection
        document.getElementById('classSelect').addEventListener('change', function() {
            const className = this.value;
            if (className) {
                selectedClass = className;
                
                // Get student count for this class
                fetch(`../../includes/get_class_student_count.php?class=${encodeURIComponent(className)}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('selectedClassName').textContent = className;
                        document.getElementById('studentCount').textContent = data.count;
                        document.getElementById('selectedClassDisplay').classList.remove('hidden');
                        checkFormComplete();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('selectedClassName').textContent = className;
                        document.getElementById('studentCount').textContent = '0';
                        document.getElementById('selectedClassDisplay').classList.remove('hidden');
                        checkFormComplete();
                    });
            } else {
                selectedClass = null;
                document.getElementById('selectedClassDisplay').classList.add('hidden');
                checkFormComplete();
            }
        });

        // Student selection
        document.querySelectorAll('.clean-student-bg-white rounded shadow').forEach(bg-white rounded shadow => {
            bg-white rounded shadow.addEventListener('click', function() {
                if (assignmentType === 'individual') {
                    // Single student selection with toggle
                    const isAlreadySelected = this.classList.contains('selected');
                    
                    // Clear all selections first
                    document.querySelectorAll('.clean-student-bg-white rounded shadow').forEach(c => c.classList.remove('selected'));
                    
                    if (!isAlreadySelected) {
                        // Select this student if it wasn't already selected
                        this.classList.add('selected');
                        selectedStudent = {
                            id: this.dataset.studentId,
                            name: this.dataset.studentName,
                            class: this.dataset.studentClass
                        };
                        selectedStudentId = selectedStudent.id;
                        
                        // Update form
                        document.getElementById('selectedStudentId').value = selectedStudentId;
                        document.getElementById('selectedStudentName').textContent = selectedStudent.name + ' (' + selectedStudent.class + ')';
                        document.getElementById('selectedStudentDisplay').classList.remove('hidden');
                    } else {
                        // Deselect - clear the student
                        selectedStudent = null;
                        selectedStudentId = null;
                        document.getElementById('selectedStudentId').value = '';
                        document.getElementById('selectedStudentDisplay').classList.add('hidden');
                    }
                    
                    document.getElementById('selectedStudentsDisplay').classList.add('hidden');
                    
                } else if (assignmentType === 'multi-student') {
                    // Multi-student selection
                    toggleStudentSelection(this.dataset.studentId);
                }
                
                checkFormComplete();
                updateAssignmentSummary();
            });
        });

        // Student search
        document.getElementById('studentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.clean-student-bg-white rounded shadow').forEach(bg-white rounded shadow => {
                const studentName = bg-white rounded shadow.dataset.studentName.toLowerCase();
                const studentClass = bg-white rounded shadow.dataset.studentClass.toLowerCase();
                
                if (studentName.includes(searchTerm) || studentClass.includes(searchTerm)) {
                    bg-white rounded shadow.style.display = 'block';
                } else {
                    bg-white rounded shadow.style.display = 'none';
                }
            });
        });

        function resetSelections() {
            selectedStudent = null;
            selectedStudentId = null;
            selectedStudents.clear();
            selectedFees.clear();
            selectedClass = null;
            
            document.querySelectorAll('.clean-student-bg-white rounded shadow').forEach(c => c.classList.remove('selected'));
            document.querySelectorAll('.clean-fee-bg-white rounded shadow').forEach(c => c.classList.remove('selected'));
            document.getElementById('selectedStudentDisplay').classList.add('hidden');
            document.getElementById('selectedStudentsDisplay').classList.add('hidden');
            document.getElementById('selectedFeesDisplay').classList.add('hidden');
            document.getElementById('selectedClassDisplay').classList.add('hidden');
            document.getElementById('classSelect').value = '';
            document.getElementById('selectedStudentId').value = '';
            document.getElementById('selectedFeesInput').value = '';
            
            updateFeeCounter();
            updateSelectedFeesDisplay();
        }

        // Reset form
        function resetForm() {
            resetSelections();
            document.getElementById('due_date').value = '';
            document.getElementById('term').value = '';
            document.getElementById('notes').value = '';
            updateAssignmentSummary();
            checkFormComplete();
        }

        // Check if form is complete and can be submitted
        function checkFormComplete() {
            const currentAssignmentType = document.getElementById('assignmentType')?.value || assignmentType;
            const hasStudent = currentAssignmentType === 'individual' && selectedStudentId;
            const hasMultiStudents = currentAssignmentType === 'multi-student' && selectedStudents.size > 0;
            const hasClass = currentAssignmentType === 'class' && document.getElementById('classSelect')?.value;
            const hasFees = selectedFees.size > 0;
            const hasDueDate = document.getElementById('due_date')?.value;
            
            const isComplete = (hasStudent || hasMultiStudents || hasClass) && hasFees && hasDueDate;
            
            const submitBtn = document.getElementById('submitBtn');
            const floatingBtn = document.getElementById('floatingSubmitBtn');
            const floatingCounter = document.getElementById('floatingCounter');
            const formStatus = document.getElementById('formStatus');
            const assignmentSummary = document.getElementById('assignmentSummary');
            
            // Update main submit button
            if (submitBtn) {
                submitBtn.disabled = !isComplete;
                submitBtn.classList.toggle('px-3 py-2 rounded-success', isComplete);
                submitBtn.classList.toggle('px-3 py-2 rounded-secondary', !isComplete);
                
                if (isComplete) {
                    submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i><span>Assign ' + selectedFees.size + ' Fee(s) Now</span>';
                    formStatus.innerHTML = '<i class="fas fa-check-circle mr-1 text-green-600"></i><span class="text-green-600">Ready to assign fees!</span>';
                    assignmentSummary.textContent = 'Ready to Assign ' + selectedFees.size + ' Fee(s)';
                    assignmentSummary.className = 'clean-badge clean-badge-success';
                } else {
                    submitBtn.innerHTML = '<i class="fas fa-times-circle mr-2"></i><span>Complete Form First</span>';
                    formStatus.innerHTML = '<i class="fas fa-info-circle mr-1"></i>Complete all required fields to enable assignment';
                    assignmentSummary.textContent = 'Incomplete Form';
                    assignmentSummary.className = 'clean-badge clean-badge-secondary';
                }
            }
            
            return isComplete;
        }

        // Handle form submission
        function handleSubmit(event) {
            if (!checkFormComplete()) {
                event.preventDefault();
                alert('Please complete all required fields and select at least one fee.');
                return false;
            }
            
            // Show loading state
            const submitBtn = event.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Assigning Fees...';
                submitBtn.disabled = true;
            }
            
            return true;
        }

        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners
            document.getElementById('due_date').addEventListener('change', checkFormComplete);
            document.getElementById('term').addEventListener('change', checkFormComplete);
            document.getElementById('classSelect').addEventListener('change', function() {
                updateAssignmentSummary();
                checkFormComplete();
            });
            
            // Set default due date (30 days from now)
            const defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 30);
            document.getElementById('due_date').value = defaultDate.toISOString().split('T')[0];
            
            // Initialize form display
            const assignmentDetails = document.getElementById('assignmentDetails');
            if (assignmentDetails) {
                assignmentDetails.style.display = 'block';
            }
            
            // Initial form checks
            updateAssignmentSummary();
            checkFormComplete();
        });
    </script>
</body>
</html>
