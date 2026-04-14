<?php
include '../../includes/db_connect.php';
include '../../includes/auth_check.php';
include '../../includes/system_settings.php';
include '../../includes/student_balance_functions.php';

// Get current term and academic year
$current_term = getCurrentTerm($conn);
$academic_year = getAcademicYear($conn);
$available_terms = getAvailableTerms();

// Build Academic Year options from data + system default
$year_options = [];
$yrs1 = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs1) {
    while ($yr = $yrs1->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs1->close();
}
$yrs2 = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs2) {
    while ($yr = $yrs2->fetch_assoc()) {
        if (!empty($yr['academic_year']) && !in_array($yr['academic_year'], $year_options, true)) { $year_options[] = $yr['academic_year']; }
    }
    $yrs2->close();
}
if (!in_array($academic_year, $year_options, true)) { array_unshift($year_options, $academic_year); }

// Get URL parameters for pre-filling
$pre_student_id = intval($_GET['student_id'] ?? 0);
$pre_fee_id = intval($_GET['fee_id'] ?? 0);
$pre_amount = floatval($_GET['amount'] ?? 0);
$pre_term = isset($_GET['term']) ? trim($_GET['term']) : '';
$pre_academic_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
$selected_term = $pre_term !== '' ? $pre_term : $current_term;
$selected_academic_year = $pre_academic_year !== '' ? $pre_academic_year : $academic_year;

// Fetch students with their current balances
$students_query = "
    SELECT 
        s.id, 
        s.first_name, 
        s.last_name, 
        s.class,
        s.status,
        COALESCE(SUM(CASE WHEN sf.status = 'pending' THEN sf.amount ELSE 0 END), 0) as outstanding_fees
    FROM students s
    LEFT JOIN student_fees sf ON s.id = sf.student_id
    WHERE s.status = 'active'
    GROUP BY s.id, s.first_name, s.last_name, s.class, s.status
    ORDER BY s.first_name, s.last_name
";
$students = $conn->query($students_query);

// Fetch all fee categories for general payments
$fees_result = $conn->query("SELECT id, name FROM fees ORDER BY name");
$fee_options = [];
while ($row = $fees_result->fetch_assoc()) {
    $fee_options[] = $row;
}

// If pre-filled with student, get their details
$selected_student = null;
if ($pre_student_id > 0) {
    $selected_student = getStudentBalance($conn, $pre_student_id);
    $outstanding_fees = getStudentOutstandingFees($conn, $pre_student_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - Salba Montessori Accounting</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="view_payments.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Payments
                </a>
            </div>
            <div class="flex justify-between items-center flex-wrap">
                <div class="mb- mb-md-0">
                    <h1 class="clean-page-title"><i class="fas fa-credit-bg-white rounded shadow mr-2"></i>Record Payment</h1>
                    <p class="clean-page-subtitle">
                        Process student fee payments with automatic balance tracking
                        <span class="clean-badge clean-badge-primary ml-2"><i class="fas fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($selected_term); ?></span>
                        <span class="clean-badge clean-badge-info ml-1"><i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $selected_academic_year)); ?></span>
                    </p>
                </div>
                <div>
                    <a href="../reports/student_balances.php" class="px-3 py-2 rounded-clean-outline mr-2">
                        <i class="fas fa-balance-scale"></i> BALANCES
                    </a>
                    <a href="view_payments.php" class="px-3 py-2 rounded-clean-success">
                        <i class="fas fa-history"></i> HISTORY
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <form action="record_payment.php" method="POST" id="paymentForm" novalidate>
            <div class="flex flex-wrap">
                <div class="lg:col-span-6 mb-">
                    <div class="bg-white rounded shadow border-0 shadow-lg">
                        <div class="bg-white rounded shadow-header bg-gradient text-white">
                            <h5 class="mb-">
                                <i class="fas fa-user mr-2"></i>Payment Type
                            </h5>
                        </div>
                        <div class="bg-white rounded shadow-body">
                            <div class="mb-">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_mode" id="mode_student" value="student" checked>
                                    <label class="form-check-label" for="mode_student">Student Payment</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_mode" id="mode_general" value="general">
                                    <label class="form-check-label" for="mode_general">General Payment (Not tied to student)</label>
                                </div>
                            </div>
                            
                            <!-- Student Payment Section -->
                            <div id="studentPaymentSection">
                                <label for="student_id" class="block text-sm font-medium mb- fw-semibold">
                                    <i class="fas fa-search mr-2"></i>Student
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white border border-gray-300 rounded px-3 py-2 bg-white-lg" id="student_id" name="student_id">
                                    <option value="">Choose a student...</option>
                                    <?php foreach($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" 
                                                data-balance="<?php echo $student['outstanding_fees']; ?>"
                                                data-class="<?php echo htmlspecialchars($student['class']); ?>"
                                                <?php echo ($pre_student_id == $student['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> 
                                            (<?php echo htmlspecialchars($student['class']); ?>)
                                            <?php if ($student['outstanding_fees'] > 0): ?>
                                                - Owes: GHâ‚µ<?php echo number_format($student['outstanding_fees'] ?? 0, 2); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Student Balance Display (Redesigned) -->
                                <div id="studentBalanceInfo" class="hidden animate-fade-in">
                                    <div class="student-balance-bg-white rounded shadow p-3 mb-">
                                        <h6 class="mb-">
                                            <i class="fas fa-info-circle mr-2"></i>Student Fee Summary
                                        </h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="fw-bold text-primary" id="totalFees">GHâ‚µ0.00</div>
                                                <small class="text-gray-600">Total Fees</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold text-green-600" id="totalPaid">GHâ‚µ0.00</div>
                                                <small class="text-gray-600">Total Payment</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold text-red-600" id="outstandingAmount">GHâ‚µ0.00</div>
                                                <small class="text-gray-600">Outstanding</small>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12 text-center">
                                                <span class="badge bg-warning text-dark fs-6" id="outstandingNotice">Outstanding Payment: GHâ‚µ<span id="outstandingNoticeValue">0.00</span></span>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12 text-center">
                                                <span class="fw-bold text-green-600" id="afterPaymentAmountLabel">After Payment: </span>
                                                <span class="fw-bold" id="afterPaymentAmount">GHâ‚µ0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Outstanding Fees -->
                                <div id="outstandingFeesSection" class="hidden animate-fade-in">
                                    <h6 class="mb-">
                                        <i class="fas fa-list mr-2"></i>Outstanding Fees (Click to auto-fill amount)
                                    </h6>
                                    <div id="outstandingFeesList">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- General Payment Section -->
                            <div id="generalPaymentSection" class="hidden">
                                <div class="p-4 bg-blue-100 text-blue-700 rounded border border-blue-200">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>General Payment:</strong> Use this for miscellaneous payments not tied to any student or specific fee category (e.g., donations, other income).
                                </div>
                                <label for="fee_id" class="block text-sm font-medium mb- fw-semibold">
                                    <i class="fas fa-list mr-2"></i>Fee Category (Optional)
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white border border-gray-300 rounded px-3 py-2 bg-white-lg" id="fee_id" name="fee_id">
                                    <option value="">None - General Payment</option>
                                    <?php foreach($fee_options as $fee): ?>
                                        <option value="<?php echo $fee['id']; ?>" <?php echo ($pre_fee_id == $fee['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fee['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-gray-600">You can optionally link this payment to a fee category for reporting purposes.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Payment Details -->
                <div class="lg:col-span-6 mb-">
                    <div class="bg-white rounded shadow border-0 shadow-lg">
                        <div class="bg-white rounded shadow-header bg-gradient text-white">
                            <h5 class="mb-">
                                <i class="fas fa-money-bill-wave mr-2"></i>Payment Details
                            </h5>
                        </div>
                        <div class="bg-white rounded shadow-body">
                            <div class="flex flex-wrap">
                                <div class="md:col-span-6 mb-">
                                    <label for="term" class="block text-sm font-medium mb- fw-semibold">
                                        <i class="fas fa-calendar-alt mr-2"></i>Term *
                                    </label>
                                    <select class="border border-gray-300 rounded px-3 py-2 bg-white border border-gray-300 rounded px-3 py-2 bg-white-lg" id="term" name="term" required>
                                        <?php foreach ($available_terms as $term): ?>
                                            <option value="<?php echo htmlspecialchars($term); ?>" 
                                                    <?php echo $term === $selected_term ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($term); ?>
                                                <?php echo $term === $current_term ? ' (Current)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-gray-600">Academic Year: <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $selected_academic_year)); ?></small>
                                </div>
                                <div class="md:col-span-6 mb-">
                                    <label for="amount" class="block text-sm font-medium mb- fw-semibold">
                                        <i class="fas fa-dollar-sign mr-2"></i>Amount (GHâ‚µ) *
                                    </label>
                                    <input type="number" step="0.01" min="0.01" class="w-full px-3 py-2 border border-gray-300 rounded w-full px-3 py-2 border border-gray-300 rounded-lg" 
                                           id="amount" name="amount" value="<?php echo $pre_amount > 0 ? number_format($pre_amount, 2, '.', '') : ''; ?>" required>
                                    <!-- Quick Amount Suggestions -->
                                    <div id="amountSuggestions" class="mt-2 hidden">
                                        <small class="text-gray-600 block mb-">Quick amounts:</small>
                                        <div class="flex flex-wrap gap-1" id="suggestionButtons">
                                            <!-- Populated by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                                <div class="md:col-span-6 mb-">
                                    <label for="academic_year" class="block text-sm font-medium mb- fw-semibold">
                                        <i class="fas fa-graduation-cap mr-2"></i>Academic Year *
                                    </label>
                                    <select class="border border-gray-300 rounded px-3 py-2 bg-white border border-gray-300 rounded px-3 py-2 bg-white-lg" id="academic_year" name="academic_year" required>
                                        <?php foreach ($year_options as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                            <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $selected_academic_year) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-gray-600">Used to scope this payment and for reports.</small>
                                </div>
                                <div class="md:col-span-6 mb-">
                                    <label for="payment_date" class="block text-sm font-medium mb- fw-semibold">
                                        <i class="fas fa-calendar mr-2"></i>Payment Date *
                                    </label>
                                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded w-full px-3 py-2 border border-gray-300 rounded-lg" id="payment_date" 
                                           name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="mb-">
                                <label for="receipt_no" class="block text-sm font-medium mb- fw-semibold">
                                    <i class="fas fa-receipt mr-2"></i>Receipt Number
                                </label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="receipt_no" name="receipt_no" 
                                       placeholder="Optional receipt number">
                            </div>
                            <div class="mb-">
                                <label for="description" class="block text-sm font-medium mb- fw-semibold">
                                    <i class="fas fa-comment mr-2"></i>Description/Notes
                                </label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded" id="description" name="description" rows="3" 
                                          placeholder="Optional payment notes or description"></textarea>
                            </div>
                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 px-3 py-2 rounded-lg" id="submitBtn">
                                    <i class="fas fa-credit-bg-white rounded shadow mr-2"></i>Record Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Actions -->
    <div class="max-w-7xl mx-auto">
        <div class="text-center mt-4 mb-">
            <div class="px-3 py-2 rounded-group" role="group">
                <a href="../dashboard.php" class="px-4 py-2 border border-gray-300 rounded">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
                <a href="../reports/student_balances.php" class="px-3 py-2 rounded px-3 py-2 rounded-outline-primary">
                    <i class="fas fa-balance-scale mr-2"></i>Student Balances
                </a>
                <a href="view_payments.php" class="px-3 py-2 rounded px-3 py-2 rounded-outline-success">
                    <i class="fas fa-history mr-2"></i>Payment History
                </a>
            </div>
        </div>
    </div>
        <script>
        // Payment mode toggle logic - SIMPLIFIED
        const modeStudent = document.getElementById('mode_student');
        const modeGeneral = document.getElementById('mode_general');
        const studentSection = document.getElementById('studentPaymentSection');
        const generalSection = document.getElementById('generalPaymentSection');
        const studentIdInput = document.getElementById('student_id');
        const feeIdInput = document.getElementById('fee_id');
        const paymentForm = document.getElementById('paymentForm');

        function togglePaymentMode() {
            if (modeStudent.checked) {
                studentSection.style.display = 'block';
                generalSection.style.display = 'none';
            } else {
                studentSection.style.display = 'none';
                generalSection.style.display = 'block';
            }
        }
        
        modeStudent.addEventListener('change', togglePaymentMode);
        modeGeneral.addEventListener('change', togglePaymentMode);
        togglePaymentMode(); // Initialize on page load

        // Custom form validation
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default submission
            
            let isValid = true;
            let errorMessage = '';
            
            // Check payment mode and validate accordingly
            const paymentMode = modeStudent.checked ? 'student' : 'general';
            
            if (paymentMode === 'student') {
                const studentId = studentIdInput.value;
                if (!studentId || studentId === '') {
                    isValid = false;
                    errorMessage = 'Please select a student';
                    studentIdInput.classList.add('is-invalid');
                } else {
                    studentIdInput.classList.remove('is-invalid');
                }
            } else {
                // General payment doesn't require fee_id - it's optional
                feeIdInput.classList.remove('is-invalid');
            }
            
            // Validate amount
            const amountInput = document.getElementById('amount');
            const amount = parseFloat(amountInput.value);
            if (!amountInput.value || isNaN(amount) || amount <= 0) {
                isValid = false;
                errorMessage = errorMessage || 'Please enter a valid payment amount';
                amountInput.classList.add('is-invalid');
            } else {
                amountInput.classList.remove('is-invalid');
            }
            
            // Validate term
            const termInput = document.getElementById('term');
            if (!termInput.value) {
                isValid = false;
                errorMessage = errorMessage || 'Please select a term';
                termInput.classList.add('is-invalid');
            } else {
                termInput.classList.remove('is-invalid');
            }
            
            // Validate payment date
            const dateInput = document.getElementById('payment_date');
            if (!dateInput.value) {
                isValid = false;
                errorMessage = errorMessage || 'Please select a payment date';
                dateInput.classList.add('is-invalid');
            } else {
                dateInput.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                alert(errorMessage);
                return false;
            }
            
            // If valid, submit the form
            this.submit();
        });

        // Student payment JS (existing logic)
        let selectedStudentId = <?php echo $pre_student_id; ?>;
        let studentBalances = {};
        let outstandingFees = {};
        <?php if ($pre_student_id > 0 && $selected_student): ?>
            selectedStudentId = <?php echo $pre_student_id; ?>;
            studentBalances[<?php echo $pre_student_id; ?>] = {
                outstanding_fees: <?php echo $selected_student['outstanding_fees']; ?>,
                total_payments: <?php echo $selected_student['total_payments']; ?>,
                net_balance: <?php echo $selected_student['net_balance']; ?>
            };
            outstandingFees[<?php echo $pre_student_id; ?>] = <?php echo json_encode($outstanding_fees); ?>;
            updateStudentInfo(<?php echo $pre_student_id; ?>);
        <?php endif; ?>
        
        if (studentIdInput) {
            studentIdInput.addEventListener('change', function() {
                const studentId = this.value;
                if (studentId) {
                    selectedStudentId = studentId;
                    loadStudentBalance(studentId);
                } else {
                    hideStudentInfo();
                }
            });
        }
        
        document.getElementById('amount').addEventListener('input', function() {
            updateAfterPaymentAmount();
        });
        function loadStudentBalance(studentId) {
            if (studentBalances[studentId]) {
                updateStudentInfo(studentId);
                return;
            }
            fetch(`../../includes/get_student_balance_ajax.php?student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    studentBalances[studentId] = data.balance;
                    outstandingFees[studentId] = data.fees;
                    updateStudentInfo(studentId);
                })
                .catch(error => {
                    console.error('Error:', error);
                    const option = document.querySelector(`option[value="${studentId}"]`);
                    if (option) {
                        const balance = parseFloat(option.dataset.balance) || 0;
                        studentBalances[studentId] = {
                            outstanding_fees: balance,
                            total_payments: 0,
                            net_balance: balance
                        };
                        outstandingFees[studentId] = [];
                        updateStudentInfo(studentId);
                    }
                });
        }
        function updateStudentInfo(studentId) {
            const balance = studentBalances[studentId];
            const fees = outstandingFees[studentId];
            if (!balance) return;
            document.getElementById('totalFees').textContent = `GHâ‚µ${(balance.total_fees).toFixed(2)}`;
            document.getElementById('totalPaid').textContent = `GHâ‚µ${balance.total_payments.toFixed(2)}`;
            document.getElementById('outstandingAmount').textContent = `GHâ‚µ${balance.outstanding_fees.toFixed(2)}`;
            document.getElementById('outstandingNoticeValue').textContent = balance.outstanding_fees.toFixed(2);
            document.getElementById('studentBalanceInfo').classList.remove('hidden');
            if (balance.outstanding_fees > 0) {
                document.getElementById('outstandingNotice').style.display = '';
            } else {
                document.getElementById('outstandingNotice').style.display = 'none';
            }
            const feesList = document.getElementById('outstandingFeesList');
            feesList.innerHTML = '';
            if (fees && fees.length > 0) {
                fees.forEach(fee => {
                    const feebg-white rounded shadow = document.createElement('div');
                    feebg-white rounded shadow.className = 'bg-white rounded shadow outstanding-fee-bg-white rounded shadow mb-';
                    feebg-white rounded shadow.innerHTML = `
                        <div class="bg-white rounded shadow-body p-3">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h6 class="mb-">${fee.fee_name}</h6>
                                    <small class="text-gray-600">Due: ${formatDate(fee.due_date)}</small>
                                </div>
                                <div class="text-right">
                                    <div class="fw-bold">GHâ‚µ${parseFloat(fee.amount).toFixed(2)}</div>
                                    <span class="badge ${getStatusBadgeClass(fee.payment_status)}">${fee.payment_status}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    feebg-white rounded shadow.addEventListener('click', function() {
                        document.querySelectorAll('.outstanding-fee-bg-white rounded shadow').forEach(bg-white rounded shadow => 
                            bg-white rounded shadow.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('amount').value = parseFloat(fee.amount).toFixed(2);
                        updateAfterPaymentAmount();
                        const description = document.getElementById('description');
                        if (!description.value) {
                            description.value = `Payment for ${fee.fee_name}`;
                        }
                    });
                    feesList.appendChild(feebg-white rounded shadow);
                });
                document.getElementById('outstandingFeesSection').classList.remove('hidden');
                createAmountSuggestions(fees, balance.outstanding_fees);
            } else {
                document.getElementById('outstandingFeesSection').classList.add('hidden');
                if (balance.outstanding_fees > 0) {
                    createAmountSuggestions([], balance.outstanding_fees);
                } else {
                    document.getElementById('amountSuggestions').classList.add('hidden');
                }
            }
            updateAfterPaymentAmount();
        }
        function createAmountSuggestions(fees, totalOutstanding) {
            const suggestionsmax-w-7xl mx-auto = document.getElementById('suggestionButtons');
            const amountSuggestions = document.getElementById('amountSuggestions');
            suggestionsmax-w-7xl mx-auto.innerHTML = '';
            if (fees.length > 0) {
                const uniqueAmounts = [...new Set(fees.map(fee => parseFloat(fee.amount)))].sort((a, b) => a - b);
                uniqueAmounts.forEach(amount => {
                    const px-3 py-2 rounded = document.createElement('button');
                    px-3 py-2 rounded.type = 'button';
                    px-3 py-2 rounded.className = 'px-3 py-2 rounded px-3 py-2 rounded-outline-primary px-3 py-2 rounded-sm amount-suggestion';
                    px-3 py-2 rounded.textContent = `GHâ‚µ${amount.toFixed(2)}`;
                    px-3 py-2 rounded.addEventListener('click', function() {
                        document.getElementById('amount').value = amount.toFixed(2);
                        updateAfterPaymentAmount();
                    });
                    suggestionsmax-w-7xl mx-auto.appendChild(px-3 py-2 rounded);
                });
            }
            if (totalOutstanding > 0) {
                const px-3 py-2 rounded = document.createElement('button');
                px-3 py-2 rounded.type = 'button';
                px-3 py-2 rounded.className = 'px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 px-3 py-2 rounded-sm amount-suggestion';
                px-3 py-2 rounded.textContent = `Pay All (GHâ‚µ${totalOutstanding.toFixed(2)})`;
                px-3 py-2 rounded.addEventListener('click', function() {
                    document.getElementById('amount').value = totalOutstanding.toFixed(2);
                    updateAfterPaymentAmount();
                });
                suggestionsmax-w-7xl mx-auto.appendChild(px-3 py-2 rounded);
            }
            if (totalOutstanding > 0) {
                amountSuggestions.classList.remove('hidden');
            }
        }
        function updateAfterPaymentAmount() {
            if (!selectedStudentId || !studentBalances[selectedStudentId]) return;
            const paymentAmount = parseFloat(document.getElementById('amount').value) || 0;
            const currentBalance = studentBalances[selectedStudentId].outstanding_fees;
            const afterPayment = Math.max(0, currentBalance - paymentAmount);
            document.getElementById('afterPaymentAmount').textContent = `GHâ‚µ${afterPayment.toFixed(2)}`;
            const afterPaymentElement = document.getElementById('afterPaymentAmount');
            if (afterPayment === 0) {
                afterPaymentElement.className = 'fw-bold text-green-600';
            } else if (afterPayment < currentBalance) {
                afterPaymentElement.className = 'fw-bold text-yellow-600';
            } else {
                afterPaymentElement.className = 'fw-bold text-red-600';
            }
        }
        function hideStudentInfo() {
            document.getElementById('studentBalanceInfo').classList.add('hidden');
            document.getElementById('outstandingFeesSection').classList.add('hidden');
            document.getElementById('amountSuggestions').classList.add('hidden');
            selectedStudentId = null;
        }
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB', { 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric' 
            });
        }
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'Overdue': return 'bg-danger';
                case 'Due Soon': return 'bg-warning';
                default: return 'bg-secondary';
            }
        }
    </script>
</body>
</html>
