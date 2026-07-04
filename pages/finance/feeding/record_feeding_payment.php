<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$current_semester = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);
$success_message = '';
$error_message = '';

function feeding_has_column($conn, $table_name, $column_name) {
    $table_name = $conn->real_escape_string($table_name);
    $column_name = $conn->real_escape_string($column_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = '$column_name'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function feeding_bind_params($stmt, string $types, array $values) {
    $refs = [];
    $refs[] = $types;
    foreach ($values as $index => $value) {
        $refs[] = &$values[$index];
    }
    return $stmt->bind_param(...$refs);
}

$has_month_no = feeding_has_column($conn, 'feeding_payments', 'month_no');
$has_months_count = feeding_has_column($conn, 'feeding_payments', 'months_count');
$has_units_paid = feeding_has_column($conn, 'feeding_payments', 'units_paid');

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $student_id = intval($_POST['student_id']);
    $student_feeding_plan_id = intval($_POST['student_feeding_plan_id']);
    $payment_date = $_POST['payment_date'];
    $payment_type = $_POST['payment_type'];
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes']);
    $month_no = intval($_POST['month_no'] ?? 0);
    $months_count = intval($_POST['months_count'] ?? 1);
    if ($months_count < 1) {
        $months_count = 1;
    }
    $units_paid = $months_count;
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    // Guard: daily payment type is no longer an active plan
    if (!in_array($payment_type, ['weekly', 'monthly'], true)) {
        $error_message = "Invalid payment type. Only weekly or monthly payments are accepted.";
    } elseif (empty($student_id) || empty($payment_date) || empty($amount) || $amount <= 0) {
        $error_message = "Please fill all required fields with valid data.";
    } elseif ($payment_type === 'monthly' && ($month_no < 1 || $month_no > 12 || $months_count < 1)) {
        $error_message = "For monthly payments, choose a valid month and months count.";
    } else {
        // Insert payment record with column-compatibility for evolving schema.
        $cols = ['student_id', 'student_feeding_plan_id', 'payment_date', 'payment_type', 'amount', 'payment_method', 'recorded_by', 'notes'];
        $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?'];
        $types = 'iissdsis';
        $values = [$student_id, $student_feeding_plan_id, $payment_date, $payment_type, $amount, $payment_method, $user_id, $notes];

        if ($has_month_no) {
            $cols[] = 'month_no';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = ($payment_type === 'monthly' ? $month_no : null);
        }
        if ($has_months_count) {
            $cols[] = 'months_count';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = ($payment_type === 'monthly' ? $months_count : 1);
        }
        if ($has_units_paid) {
            $cols[] = 'units_paid';
            $placeholders[] = '?';
            $types .= 'd';
            $values[] = (float)$units_paid;
        }

        $insert_query = "INSERT INTO feeding_payments (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($insert_query);
        feeding_bind_params($stmt, $types, $values);
        
        if ($stmt->execute()) {
            $success_message = "Feeding payment recorded successfully!";
            // Reset form
            $_POST = array();
        } else {
            if (strpos($stmt->error, 'Duplicate entry') !== false) {
                $error_message = "A payment for this student on this date already exists.";
            } else {
                $error_message = "Error recording payment: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Get students on active feeding plans
$students_query = "
    SELECT 
        dwf.id,
        s.id as student_id,
        s.first_name,
        s.last_name,
        CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
        dwf.plan_type,
        dwf.amount_per_unit,
        s.class as class_name
    FROM student_daily_weekly_feeding dwf
    JOIN students s ON dwf.student_id = s.id
    WHERE dwf.academic_year = '$acad_year'
    AND dwf.semester = '$current_semester'
    AND dwf.status = 'active'
    ORDER BY s.first_name, s.last_name
";

$students_result = $conn->query($students_query);
$students = $students_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Feeding Payment | Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>
    
    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="daily_weekly_tracker.php" class="hover:text-blue-600">Feeding Tracker</a>
                <span>/</span>
                <span class="text-blue-600">Record Payment</span>
            </div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-receipt text-emerald-600"></i> Record Feeding Payment
            </h1>
        </div>

        <!-- Content -->
        <div class="px-6 max-w-4xl mx-auto">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-3">
                    <i class="fas fa-check-circle text-emerald-600 text-lg"></i>
                    <span class="text-emerald-700 font-medium"><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
                    <span class="text-red-700 font-medium"><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
                <form method="POST" class="p-6 space-y-6">
                    <!-- Student Selection -->
                    <div>
                        <label for="student_id" class="block text-sm font-semibold text-slate-700 mb-2">
                            Select Student <span class="text-red-600">*</span>
                        </label>
                        <select name="student_id" id="student_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updatePlanInfo()" required>
                            <option value="">-- Choose a student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['student_id'] ?>" 
                                    data-plan-id="<?= $student['id'] ?>"
                                    data-plan-type="<?= htmlspecialchars($student['plan_type']) ?>"
                                    data-amount="<?= htmlspecialchars($student['amount_per_unit']) ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> (<?= htmlspecialchars($student['student_code']) ?>) - <?= ucfirst($student['plan_type']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Plan Info Display -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Payment Plan</label>
                            <input type="text" id="plan_type_display" class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50" readonly>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Standard Amount</label>
                            <input type="text" id="amount_display" class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50" readonly>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="payment_date" class="block text-sm font-semibold text-slate-700 mb-2">
                                Payment Date <span class="text-red-600">*</span>
                            </label>
                            <input type="date" name="payment_date" id="payment_date" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <div>
                            <label for="payment_type" class="block text-sm font-semibold text-slate-700 mb-2">
                                Payment Type <span class="text-red-600">*</span>
                            </label>
                            <select name="payment_type" id="payment_type" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500" onchange="recalculateAmount()" required>
                                <option value="">-- Select --</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="amount" class="block text-sm font-semibold text-slate-700 mb-2">
                                Amount Paid <span class="text-red-600">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-700 font-medium">GHS</span>
                                <input type="number" name="amount" id="amount" class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div>
                            <label for="payment_method" class="block text-sm font-semibold text-slate-700 mb-2">
                                Payment Method <span class="text-red-600">*</span>
                            </label>
                            <select name="payment_method" id="payment_method" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500" required>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="transfer">Bank Transfer</option>
                                <option value="momo">Mobile Money</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="month_no" class="block text-sm font-semibold text-slate-700 mb-2">Month (Monthly only)</label>
                            <select name="month_no" id="month_no" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500" onchange="recalculateAmount()">
                                <option value="0">-- Select Month --</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label for="months_count" class="block text-sm font-semibold text-slate-700 mb-2">Months Count (Monthly only)</label>
                            <input type="number" name="months_count" id="months_count" min="1" max="12" value="1" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500" oninput="recalculateAmount()">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-semibold text-slate-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Add any additional notes about this payment..."></textarea>
                    </div>

                    <!-- Hidden field for plan ID -->
                    <input type="hidden" name="student_feeding_plan_id" id="student_feeding_plan_id" value="">

                    <!-- Buttons -->
                    <div class="flex gap-4 justify-end pt-4">
                        <a href="daily_weekly_tracker.php" class="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" name="submit_payment" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2">
                            <i class="fas fa-check"></i> Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function formatAmount(value) {
            const num = Number(value);
            if (!Number.isFinite(num) || num < 0) {
                return '0.00';
            }
            return num.toFixed(2);
        }

        function toggleMonthlyFields(isMonthly) {
            const monthSelect = document.getElementById('month_no');
            const monthsInput = document.getElementById('months_count');

            monthSelect.disabled = !isMonthly;
            monthsInput.disabled = !isMonthly;
            monthSelect.required = isMonthly;

            monthSelect.classList.toggle('opacity-50', !isMonthly);
            monthsInput.classList.toggle('opacity-50', !isMonthly);

            if (!isMonthly) {
                monthSelect.value = '0';
                monthsInput.value = '1';
            }
        }

        function recalculateAmount() {
            const studentSelect = document.getElementById('student_id');
            const paymentType = document.getElementById('payment_type').value;
            const monthsCountRaw = document.getElementById('months_count').value;
            const monthsCount = Math.max(1, Number(monthsCountRaw || 1));
            const amountInput = document.getElementById('amount');

            const option = studentSelect.options[studentSelect.selectedIndex];
            const standardAmount = option && option.dataset.amount ? Number(option.dataset.amount) : 0;

            const isMonthly = paymentType === 'monthly';
            toggleMonthlyFields(isMonthly);

            if (!standardAmount || !paymentType) {
                amountInput.value = '';
                return;
            }

            const computed = isMonthly ? (standardAmount * monthsCount) : standardAmount;
            amountInput.value = formatAmount(computed);
        }

        function updatePlanInfo() {
            const select = document.getElementById('student_id');
            const option = select.options[select.selectedIndex];

            const selectedPlan = option && option.dataset.planType ? option.dataset.planType : '';
            document.getElementById('plan_type_display').value = selectedPlan ? selectedPlan.toUpperCase() : '';
            document.getElementById('amount_display').value = option && option.dataset.amount ? 'GHS ' + parseFloat(option.dataset.amount).toFixed(2) : '';
            document.getElementById('student_feeding_plan_id').value = option && option.dataset.planId ? option.dataset.planId : '';

            if (selectedPlan) {
                document.getElementById('payment_type').value = selectedPlan;
            }

            recalculateAmount();
        }

        // Set today's date as default
        document.getElementById('payment_date').valueAsDate = new Date();
        toggleMonthlyFields(false);
    </script>
</body>
</html>
