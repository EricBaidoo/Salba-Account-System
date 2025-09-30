<?php
include '../includes/db_connect.php';
include '../includes/auth_check.php';
include '../includes/student_balance_functions.php';

// Get URL parameters for pre-filling
$pre_student_id = intval($_GET['student_id'] ?? 0);
$pre_fee_id = intval($_GET['fee_id'] ?? 0);
$pre_amount = floatval($_GET['amount'] ?? 0);

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .payment-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .student-balance-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            border: 2px solid #667eea;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        .outstanding-fee-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .outstanding-fee-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        .outstanding-fee-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
        }
        .amount-suggestion {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .amount-suggestion:hover {
            background: #667eea !important;
            color: white !important;
            transform: translateY(-1px);
        }
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- Navigation & Header -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <strong>Salba Montessori</strong>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                <a class="nav-link" href="view_students.php"><i class="fas fa-user-graduate me-2"></i>Students</a>
                <a class="nav-link" href="view_fees.php"><i class="fas fa-money-check-alt me-2"></i>Fees</a>
                <a class="nav-link" href="view_payments.php"><i class="fas fa-credit-card me-2"></i>Payments</a>
                <a class="nav-link" href="view_expenses.php"><i class="fas fa-file-invoice-dollar me-2"></i>Expenses</a>
                <a class="nav-link" href="report.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
            </div>
        </div>
    </nav>

    <!-- Professional Header -->
    <div class="payment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-credit-card me-3"></i>
                        Record Payment
                    </h1>
                    <p class="lead mb-0">Process student fee payments with automatic balance tracking</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="student_balances.php" class="btn btn-light me-2">
                        <i class="fas fa-balance-scale me-2"></i>View Balances
                    </a>
                    <a href="view_payments.php" class="btn btn-outline-light">
                        <i class="fas fa-history me-2"></i>Payment History
                    </a>
                </div>
            </div>
        </div>
        <!-- Quick Links Row -->
        <div class="container mt-3">
            <div class="row g-2 justify-content-center">
                <div class="col-auto">
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="fas fa-home me-1"></i>Dashboard</a>
                </div>
                <div class="col-auto">
                    <a href="view_students.php" class="btn btn-outline-light btn-sm"><i class="fas fa-user-graduate me-1"></i>Students</a>
                </div>
                <div class="col-auto">
                    <a href="view_fees.php" class="btn btn-outline-light btn-sm"><i class="fas fa-money-check-alt me-1"></i>Fees</a>
                </div>
                <div class="col-auto">
                    <a href="view_payments.php" class="btn btn-outline-light btn-sm"><i class="fas fa-credit-card me-1"></i>Payments</a>
                </div>
                <div class="col-auto">
                    <a href="view_expenses.php" class="btn btn-outline-light btn-sm"><i class="fas fa-file-invoice-dollar me-1"></i>Expenses</a>
                </div>
                <div class="col-auto">
                    <a href="report.php" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar me-1"></i>Reports</a>
                </div>
            </div>
        </div>
    </div>


    <div class="container">
        <form action="record_payment.php" method="POST" id="paymentForm">
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-lg">
                        <div class="card-header bg-gradient text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2"></i>Payment Type
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_mode" id="mode_student" value="student" checked>
                                    <label class="form-check-label" for="mode_student">Student Payment</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_mode" id="mode_general" value="general">
                                    <label class="form-check-label" for="mode_general">General/Category Payment</label>
                                </div>
                            </div>
                            <!-- Student Payment Section -->
                            <div id="studentPaymentSection">
                                <label for="student_id" class="form-label fw-semibold">
                                    <i class="fas fa-search me-2"></i>Student
                                </label>
                                <select class="form-select form-select-lg" id="student_id" name="student_id">
                                    <option value="">Choose a student...</option>
                                    <?php foreach($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" 
                                                data-balance="<?php echo $student['outstanding_fees']; ?>"
                                                data-class="<?php echo htmlspecialchars($student['class']); ?>"
                                                <?php echo ($pre_student_id == $student['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> 
                                            (<?php echo htmlspecialchars($student['class']); ?>)
                                            <?php if ($student['outstanding_fees'] > 0): ?>
                                                - Owes: GH₵<?php echo number_format($student['outstanding_fees'] ?? 0, 2); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Student Balance Display (Redesigned) -->
                                <div id="studentBalanceInfo" class="d-none animate-fade-in">
                                    <div class="student-balance-card p-3 mb-3">
                                        <h6 class="mb-2">
                                            <i class="fas fa-info-circle me-2"></i>Student Fee Summary
                                        </h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="fw-bold text-primary" id="totalFees">GH₵0.00</div>
                                                <small class="text-muted">Total Fees</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold text-success" id="totalPaid">GH₵0.00</div>
                                                <small class="text-muted">Total Payment</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold text-danger" id="outstandingAmount">GH₵0.00</div>
                                                <small class="text-muted">Outstanding</small>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12 text-center">
                                                <span class="badge bg-warning text-dark fs-6" id="outstandingNotice" style="display:none;">Outstanding Payment: GH₵<span id="outstandingNoticeValue">0.00</span></span>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12 text-center">
                                                <span class="fw-bold text-success" id="afterPaymentAmountLabel">After Payment: </span>
                                                <span class="fw-bold" id="afterPaymentAmount">GH₵0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Outstanding Fees -->
                                <div id="outstandingFeesSection" class="d-none animate-fade-in">
                                    <h6 class="mb-3">
                                        <i class="fas fa-list me-2"></i>Outstanding Fees (Click to auto-fill amount)
                                    </h6>
                                    <div id="outstandingFeesList">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                            <!-- General Payment Section -->
                            <div id="generalPaymentSection" style="display:none;">
                                <label for="fee_id" class="form-label fw-semibold">
                                    <i class="fas fa-list me-2"></i>Fee Category
                                </label>
                                <select class="form-select form-select-lg" id="fee_id" name="fee_id">
                                    <option value="">Choose a fee category...</option>
                                    <?php foreach($fee_options as $fee): ?>
                                        <option value="<?php echo $fee['id']; ?>" <?php echo ($pre_fee_id == $fee['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fee['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Payment Details -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-lg">
                        <div class="card-header bg-gradient text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-money-bill-wave me-2"></i>Payment Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="amount" class="form-label fw-semibold">
                                        <i class="fas fa-dollar-sign me-2"></i>Amount (GH₵) *
                                    </label>
                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-lg" 
                                           id="amount" name="amount" value="<?php echo $pre_amount > 0 ? number_format($pre_amount, 2, '.', '') : ''; ?>" required>
                                    <!-- Quick Amount Suggestions -->
                                    <div id="amountSuggestions" class="mt-2 d-none">
                                        <small class="text-muted d-block mb-1">Quick amounts:</small>
                                        <div class="d-flex flex-wrap gap-1" id="suggestionButtons">
                                            <!-- Populated by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="payment_date" class="form-label fw-semibold">
                                        <i class="fas fa-calendar me-2"></i>Payment Date *
                                    </label>
                                    <input type="date" class="form-control form-control-lg" id="payment_date" 
                                           name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="receipt_no" class="form-label fw-semibold">
                                    <i class="fas fa-receipt me-2"></i>Receipt Number
                                </label>
                                <input type="text" class="form-control" id="receipt_no" name="receipt_no" 
                                       placeholder="Optional receipt number">
                            </div>
                            <div class="mb-4">
                                <label for="description" class="form-label fw-semibold">
                                    <i class="fas fa-comment me-2"></i>Description/Notes
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Optional payment notes or description"></textarea>
                            </div>
                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                    <i class="fas fa-credit-card me-2"></i>Record Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Actions -->
    <div class="container">
        <div class="text-center mt-4 mb-5">
            <div class="btn-group" role="group">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
                <a href="student_balances.php" class="btn btn-outline-primary">
                    <i class="fas fa-balance-scale me-2"></i>Student Balances
                </a>
                <a href="view_payments.php" class="btn btn-outline-success">
                    <i class="fas fa-history me-2"></i>Payment History
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment mode toggle logic
        const modeStudent = document.getElementById('mode_student');
        const modeGeneral = document.getElementById('mode_general');
        const studentSection = document.getElementById('studentPaymentSection');
        const generalSection = document.getElementById('generalPaymentSection');
        const studentIdInput = document.getElementById('student_id');
        const feeIdInput = document.getElementById('fee_id');

        function togglePaymentMode() {
            if (modeStudent.checked) {
                studentSection.style.display = '';
                generalSection.style.display = 'none';
                studentIdInput.required = true;
                feeIdInput.required = false;
            } else {
                studentSection.style.display = 'none';
                generalSection.style.display = '';
                studentIdInput.required = false;
                feeIdInput.required = true;
            }
        }
        modeStudent.addEventListener('change', togglePaymentMode);
        modeGeneral.addEventListener('change', togglePaymentMode);
        togglePaymentMode();

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
            fetch(`../includes/get_student_balance_ajax.php?student_id=${studentId}`)
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
            document.getElementById('totalFees').textContent = `GH₵${(balance.total_fees).toFixed(2)}`;
            document.getElementById('totalPaid').textContent = `GH₵${balance.total_payments.toFixed(2)}`;
            document.getElementById('outstandingAmount').textContent = `GH₵${balance.outstanding_fees.toFixed(2)}`;
            document.getElementById('outstandingNoticeValue').textContent = balance.outstanding_fees.toFixed(2);
            document.getElementById('studentBalanceInfo').classList.remove('d-none');
            if (balance.outstanding_fees > 0) {
                document.getElementById('outstandingNotice').style.display = '';
            } else {
                document.getElementById('outstandingNotice').style.display = 'none';
            }
            const feesList = document.getElementById('outstandingFeesList');
            feesList.innerHTML = '';
            if (fees && fees.length > 0) {
                fees.forEach(fee => {
                    const feeCard = document.createElement('div');
                    feeCard.className = 'card outstanding-fee-card mb-2';
                    feeCard.innerHTML = `
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">${fee.fee_name}</h6>
                                    <small class="text-muted">Due: ${formatDate(fee.due_date)}</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">GH₵${parseFloat(fee.amount).toFixed(2)}</div>
                                    <span class="badge ${getStatusBadgeClass(fee.payment_status)}">${fee.payment_status}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    feeCard.addEventListener('click', function() {
                        document.querySelectorAll('.outstanding-fee-card').forEach(card => 
                            card.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('amount').value = parseFloat(fee.amount).toFixed(2);
                        updateAfterPaymentAmount();
                        const description = document.getElementById('description');
                        if (!description.value) {
                            description.value = `Payment for ${fee.fee_name}`;
                        }
                    });
                    feesList.appendChild(feeCard);
                });
                document.getElementById('outstandingFeesSection').classList.remove('d-none');
                createAmountSuggestions(fees, balance.outstanding_fees);
            } else {
                document.getElementById('outstandingFeesSection').classList.add('d-none');
                if (balance.outstanding_fees > 0) {
                    createAmountSuggestions([], balance.outstanding_fees);
                } else {
                    document.getElementById('amountSuggestions').classList.add('d-none');
                }
            }
            updateAfterPaymentAmount();
        }
        function createAmountSuggestions(fees, totalOutstanding) {
            const suggestionsContainer = document.getElementById('suggestionButtons');
            const amountSuggestions = document.getElementById('amountSuggestions');
            suggestionsContainer.innerHTML = '';
            if (fees.length > 0) {
                const uniqueAmounts = [...new Set(fees.map(fee => parseFloat(fee.amount)))].sort((a, b) => a - b);
                uniqueAmounts.forEach(amount => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-outline-primary btn-sm amount-suggestion';
                    btn.textContent = `GH₵${amount.toFixed(2)}`;
                    btn.addEventListener('click', function() {
                        document.getElementById('amount').value = amount.toFixed(2);
                        updateAfterPaymentAmount();
                    });
                    suggestionsContainer.appendChild(btn);
                });
            }
            if (totalOutstanding > 0) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-success btn-sm amount-suggestion';
                btn.textContent = `Pay All (GH₵${totalOutstanding.toFixed(2)})`;
                btn.addEventListener('click', function() {
                    document.getElementById('amount').value = totalOutstanding.toFixed(2);
                    updateAfterPaymentAmount();
                });
                suggestionsContainer.appendChild(btn);
            }
            if (totalOutstanding > 0) {
                amountSuggestions.classList.remove('d-none');
            }
        }
        function updateAfterPaymentAmount() {
            if (!selectedStudentId || !studentBalances[selectedStudentId]) return;
            const paymentAmount = parseFloat(document.getElementById('amount').value) || 0;
            const currentBalance = studentBalances[selectedStudentId].outstanding_fees;
            const afterPayment = Math.max(0, currentBalance - paymentAmount);
            document.getElementById('afterPaymentAmount').textContent = `GH₵${afterPayment.toFixed(2)}`;
            const afterPaymentElement = document.getElementById('afterPaymentAmount');
            if (afterPayment === 0) {
                afterPaymentElement.className = 'fw-bold text-success';
            } else if (afterPayment < currentBalance) {
                afterPaymentElement.className = 'fw-bold text-warning';
            } else {
                afterPaymentElement.className = 'fw-bold text-danger';
            }
        }
        function hideStudentInfo() {
            document.getElementById('studentBalanceInfo').classList.add('d-none');
            document.getElementById('outstandingFeesSection').classList.add('d-none');
            document.getElementById('amountSuggestions').classList.add('d-none');
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