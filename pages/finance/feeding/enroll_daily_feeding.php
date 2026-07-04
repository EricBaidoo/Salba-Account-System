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

function feeding_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function feeding_normalize_key($value) {
    return strtolower(trim((string)$value));
}

function feeding_lookup_class_rate($conn, $class_name, $plan_type) {
    $stmt = $conn->prepare("SELECT amount FROM feeding_class_rates WHERE is_active = 1 AND CONVERT(class_name USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci AND plan_type = ? AND effective_from <= CURDATE() ORDER BY effective_from DESC, id DESC LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $class_name, $plan_type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (float)$row['amount'] : null;
}

$has_student_fees_table = feeding_table_exists($conn, 'student_fees');
$has_rates_table = feeding_table_exists($conn, 'feeding_class_rates');
$class_rate_map = [];

if ($has_rates_table) {
    $rate_res = $conn->query("SELECT class_name, plan_type, amount FROM feeding_class_rates WHERE is_active = 1 AND effective_from <= CURDATE() ORDER BY effective_from DESC, id DESC");
    if ($rate_res) {
        while ($row = $rate_res->fetch_assoc()) {
            $class_key = feeding_normalize_key($row['class_name'] ?? '');
            $plan_key = feeding_normalize_key($row['plan_type'] ?? '');
            if ($class_key === '' || $plan_key === '') {
                continue;
            }
            $rate_key = $class_key . '|' . $plan_key;
            if (!isset($class_rate_map[$rate_key])) {
                $class_rate_map[$rate_key] = (float)$row['amount'];
            }
        }
    }
}

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $student_id  = intval($_POST['student_id']);
    $plan_type   = $_POST['plan_type'];
    $notes       = trim($_POST['notes']);
    $confirm_fee_sync = isset($_POST['confirm_fee_sync']) ? 1 : 0;

    // Guard: only weekly or monthly are active operational plans
    if (!in_array($plan_type, ['weekly', 'monthly'], true)) {
        $error_message = "Invalid plan type. Only weekly or monthly feeding plans are available.";
    } else {
        $student_class = '';
        $student_stmt = $conn->prepare("SELECT class FROM students WHERE id = ? LIMIT 1");
        if ($student_stmt) {
            $student_stmt->bind_param('i', $student_id);
            $student_stmt->execute();
            $student_row = $student_stmt->get_result()->fetch_assoc();
            $student_stmt->close();
            $student_class = trim($student_row['class'] ?? '');
        }

        $amount_per_unit = null;
        if ($student_class !== '' && $plan_type !== '') {
            $amount_per_unit = feeding_lookup_class_rate($conn, $student_class, $plan_type);
        }

        if (empty($student_id) || $student_class === '') {
            $error_message = "Please choose a learner with an assigned class and a payment frequency.";
        } elseif ($amount_per_unit === null || $amount_per_unit <= 0) {
            $error_message = "No active feeding rate was found for {$student_class} ({$plan_type}). Set it in Feeding Settings first.";
        } elseif (!$confirm_fee_sync) {
            $error_message = "Please confirm that the Feeding Fee should be removed from the semester bill.";
        } else {
            $check_query = "SELECT id FROM student_daily_weekly_feeding
                           WHERE student_id = ? AND academic_year = ? AND semester = ? AND status = 'active'";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("iss", $student_id, $acad_year, $current_semester);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error_message = "This learner is already enrolled in a weekly/monthly feeding plan for this semester.";
            } else {
                $class_id = null;
                $insert_query = "INSERT INTO student_daily_weekly_feeding
                                (student_id, class_id, plan_type, amount_per_unit, academic_year, semester, status, notes)
                                VALUES (?, ?, ?, ?, ?, ?, 'active', ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("iisdsss", $student_id, $class_id, $plan_type, $amount_per_unit, $acad_year, $current_semester, $notes);

                if ($insert_stmt->execute()) {
                    if ($has_student_fees_table) {
                        $feeding_fee_stmt = $conn->prepare("SELECT id FROM fees WHERE name = 'Feeding Fee' LIMIT 1");
                        if ($feeding_fee_stmt) {
                            $feeding_fee_stmt->execute();
                            $feeding_fee_row = $feeding_fee_stmt->get_result()->fetch_assoc();
                            $feeding_fee_stmt->close();
                            if ($feeding_fee_row) {
                                $feeding_fee_id = (int)$feeding_fee_row['id'];
                                $remove_stmt = $conn->prepare("DELETE FROM student_fees WHERE student_id = ? AND fee_id = ? AND semester = ? AND academic_year = ? AND status != 'paid'");
                                if ($remove_stmt) {
                                    $remove_stmt->bind_param('iiss', $student_id, $feeding_fee_id, $current_semester, $acad_year);
                                    $remove_stmt->execute();
                                    $remove_stmt->close();
                                }
                            }
                        }
                    }
                    $success_message = "Learner enrolled in " . ucfirst($plan_type) . " feeding plan successfully!";
                    $_POST = [];
                } else {
                    $error_message = "Error enrolling learner: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    } // end plan_type guard
}

// Get students not yet on feeding plans
$students_query = "
    SELECT s.id, s.first_name, s.last_name, CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code, s.class as class_name
    FROM students s
    WHERE s.status = 'active'
    AND s.id NOT IN (
        SELECT student_id FROM student_daily_weekly_feeding 
        WHERE academic_year = '$acad_year' AND semester = '$current_semester' AND status = 'active'
    )
    ORDER BY s.first_name, s.last_name
";

$students_result = $conn->query($students_query);
$students = $students_result->fetch_all(MYSQLI_ASSOC);
$class_rate_map_json = json_encode($class_rate_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for Feeding | Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .plan-pill[data-selected="1"] { border-color: #2563eb; background: #eff6ff; }
        .plan-pill[data-selected="1"] .plan-radio { border-color: #2563eb; background: #2563eb; box-shadow: inset 0 0 0 4px #fff; }
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
                <span class="text-blue-600">Register for Feeding</span>
            </div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-user-plus text-blue-600"></i> Register for Feeding
            </h1>
        </div>

        <!-- Content -->
        <div class="px-6 max-w-4xl mx-auto">
            <!-- Messages -->
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
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                <form method="POST" class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-xl border border-emerald-100 bg-emerald-50/70 mb-2">
                        <div>
                            <p class="text-[0.65rem] font-black uppercase tracking-widest text-emerald-700">Source of truth</p>
                            <p class="text-sm font-semibold text-slate-900 mt-1">Amount is pulled from class rates in Feeding Settings.</p>
                        </div>
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="bg-white rounded-lg border border-emerald-100 p-3">
                                <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-500">Current class</p>
                                <p id="class_preview" class="text-sm font-semibold text-slate-900 mt-1">Select a student</p>
                            </div>
                            <div class="bg-white rounded-lg border border-emerald-100 p-3">
                                <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-500">Resolved rate</p>
                                <p id="rate_preview" class="text-sm font-semibold text-slate-900 mt-1">Choose student and frequency</p>
                            </div>
                            <div class="bg-white rounded-lg border border-emerald-100 p-3 sm:col-span-2">
                                <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-500">Current month school days</p>
                                <p id="school_days_preview" class="text-sm font-semibold text-slate-900 mt-1">Calculating...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Student Selection -->
                    <div>
                        <label for="student_id" class="block text-sm font-semibold text-slate-700 mb-2">
                            Select Student <span class="text-red-600">*</span>
                        </label>
                        <select name="student_id" id="student_id" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="updateRatePreview()" required>
                            <option value="">-- Choose a student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" data-class="<?= htmlspecialchars($student['class_name'] ?? '') ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> (<?= htmlspecialchars($student['student_code']) ?>) - <?= htmlspecialchars($student['class_name'] ?? 'No Class') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Plan Type -->
                    <div>
                        <label for="plan_type" class="block text-sm font-semibold text-slate-700 mb-2">
                            Payment Frequency <span class="text-red-600">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="plan-pill flex items-center gap-3 p-3 border border-slate-300 rounded-lg cursor-pointer hover:bg-purple-50 transition-colors" data-selected="0">
                                <input type="radio" name="plan_type" value="weekly" required class="w-4 h-4 plan-radio" onchange="updateRatePreview()">
                                <div>
                                    <span class="text-sm font-semibold block">Weekly</span>
                                    <span class="text-xs text-slate-500">Pay in installments within the week</span>
                                </div>
                            </label>
                            <label class="plan-pill flex items-center gap-3 p-3 border border-slate-300 rounded-lg cursor-pointer hover:bg-green-50 transition-colors" data-selected="0">
                                <input type="radio" name="plan_type" value="monthly" required class="w-4 h-4 plan-radio" onchange="updateRatePreview()">
                                <div>
                                    <span class="text-sm font-semibold block">Monthly</span>
                                    <span class="text-xs text-slate-500">Mon-Fri school days, excl. holidays</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Amount per Unit -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">
                            Amount per Unit (GHS)
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-700 font-medium">GHS</span>
                            <input type="text" id="amount_per_unit" class="flex-1 px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-900 font-semibold" readonly>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">This value is pulled from class rate settings; monthly rates are based on Mon-Fri school days.</p>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-semibold text-slate-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Add any notes about this enrollment..."></textarea>
                    </div>

                    <!-- Academic Info Display -->
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                        <p class="text-xs text-slate-600 mb-2"><strong>Current Period:</strong></p>
                        <p class="text-sm text-slate-900 font-medium"><?= htmlspecialchars($current_semester) ?> (<?= htmlspecialchars($acad_year) ?>)</p>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <p class="text-xs text-blue-700 font-bold uppercase tracking-widest mb-1">Enrollment Sync</p>
                        <p class="text-sm text-blue-900">When you save this learner, the system will use the class-based fee, create the feeding record, and remove the Feeding Fee from the bill automatically.</p>
                    </div>

                    <div class="bg-amber-50 p-4 rounded-lg border border-amber-200">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="confirm_fee_sync" required class="mt-1 w-4 h-4 text-amber-600 border-slate-300 rounded">
                            <span class="text-sm text-amber-900">
                                I confirm this learner should move to separate feeding and the Feeding Fee should be removed from the semester bill.
                            </span>
                        </label>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-4 justify-end pt-4 border-t border-slate-200">
                        <a href="daily_weekly_tracker.php" class="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" name="enroll_student" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                            <i class="fas fa-check"></i> Register for Feeding
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        const classRateMap = <?= $class_rate_map_json ?: '{}' ?>;

        function normalizeKey(value) {
            return String(value || '').trim().toLowerCase();
        }

        function getSelectedPlanType() {
            const selected = document.querySelector('input[name="plan_type"]:checked');
            return selected ? selected.value : '';
        }

        function getCurrentMonthSchoolDays() {
            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            let schoolDays = 0;

            for (let day = 1; day <= daysInMonth; day++) {
                const current = new Date(year, month, day);
                const weekday = current.getDay();
                if (weekday >= 1 && weekday <= 5) {
                    schoolDays++;
                }
            }

            return schoolDays;
        }

        function updateRatePreview() {
            const studentSelect = document.getElementById('student_id');
            const amountInput = document.getElementById('amount_per_unit');
            const classPreview = document.getElementById('class_preview');
            const ratePreview = document.getElementById('rate_preview');
            const schoolDaysPreview = document.getElementById('school_days_preview');
            const planType = getSelectedPlanType();
            const option = studentSelect.options[studentSelect.selectedIndex];
            const className = option ? (option.dataset.class || '') : '';
            const schoolDays = getCurrentMonthSchoolDays();

            schoolDaysPreview.textContent = schoolDays + ' days';

            classPreview.textContent = className || 'Select a student';

            document.querySelectorAll('.plan-pill').forEach(label => {
                const radio = label.querySelector('input[type="radio"]');
                label.dataset.selected = radio && radio.checked ? '1' : '0';
            });

            if (!className || !planType) {
                amountInput.value = '';
                ratePreview.textContent = 'Choose student and frequency';
                ratePreview.className = 'text-sm font-semibold text-slate-900 mt-1';
                return;
            }

            const rateKey = normalizeKey(className) + '|' + normalizeKey(planType);
            const amount = classRateMap[rateKey];

            if (typeof amount === 'undefined') {
                amountInput.value = '';
                ratePreview.textContent = 'No active rate found for this class and frequency';
                ratePreview.className = 'text-sm font-semibold text-rose-600 mt-1';
                return;
            }

            const formatted = Number(amount).toFixed(2);
            amountInput.value = formatted;
            if (planType === 'monthly') {
                ratePreview.textContent = 'GHS ' + formatted + ' per monthly cycle (' + schoolDays + ' school days)';
            } else {
                ratePreview.textContent = 'GHS ' + formatted + ' per ' + planType;
            }
            ratePreview.className = 'text-sm font-semibold text-slate-900 mt-1';
        }

        document.addEventListener('change', function(event) {
            if (event.target && (event.target.id === 'student_id' || event.target.name === 'plan_type')) {
                updateRatePreview();
            }
        });

        document.addEventListener('DOMContentLoaded', updateRatePreview);
    </script>
</body>
</html>
