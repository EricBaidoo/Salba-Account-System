<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';
include '../../../includes/feeding_helpers.php'; // feeding_week_interval(), feeding_days_in_month()

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$current_semester = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);
$success = '';
$error = '';
$warning = '';
$selected_class = trim($_GET['class'] ?? '');
$selected_date = trim($_GET['date'] ?? date('Y-m-d'));

function feed_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function feed_has_column($conn, $table_name, $column_name) {
    $table_name = $conn->real_escape_string($table_name);
    $column_name = $conn->real_escape_string($column_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name' AND COLUMN_NAME = '$column_name'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function feed_bind_params($stmt, string $types, array $values) {
    $refs = [];
    $refs[] = $types;
    foreach ($values as $index => $value) {
        $refs[] = &$values[$index];
    }
    return $stmt->bind_param(...$refs);
}

$has_plan_table = feed_table_exists($conn, 'student_daily_weekly_feeding');
$has_payments_table = feed_table_exists($conn, 'feeding_payments');
$schema_ready = $has_plan_table && $has_payments_table;

$has_payment_method = $schema_ready && feed_has_column($conn, 'feeding_payments', 'payment_method');
$has_recorded_by = $schema_ready && feed_has_column($conn, 'feeding_payments', 'recorded_by');
$has_month_no = $schema_ready && feed_has_column($conn, 'feeding_payments', 'month_no');
$has_months_count = $schema_ready && feed_has_column($conn, 'feeding_payments', 'months_count');
$has_units_paid = $schema_ready && feed_has_column($conn, 'feeding_payments', 'units_paid');
$has_rates_table = feed_table_exists($conn, 'feeding_class_rates');
$has_closeouts_table = feed_table_exists($conn, 'feeding_day_closeouts');
$has_closeout_reopened_by = $has_closeouts_table && feed_has_column($conn, 'feeding_day_closeouts', 'reopened_by');
$has_closeout_reopened_at = $has_closeouts_table && feed_has_column($conn, 'feeding_day_closeouts', 'reopened_at');

// Weekly expected adjustments (e.g., sickness days) table
$conn->query("CREATE TABLE IF NOT EXISTS feeding_weekly_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    student_feeding_plan_id INT NOT NULL,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    academic_year VARCHAR(30) NOT NULL,
    semester VARCHAR(100) NOT NULL,
    days_missed TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reason VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_plan_week (student_id, student_feeding_plan_id, week_start_date, week_end_date, academic_year, semester),
    KEY idx_week_lookup (week_start_date, week_end_date, academic_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$has_weekly_adjustments_table = feed_table_exists($conn, 'feeding_weekly_adjustments');

// Fetch academic calendar closures (holidays/breaks)
$calendar_closures = [];
$closure_dates = [];
$cal_res = $conn->query("SELECT event_date, event_type, description FROM academic_calendar WHERE event_type IN ('holiday', 'break', 'mid-term') ORDER BY event_date ASC");
if ($cal_res) {
    while($c = $cal_res->fetch_assoc()) {
        $closure_dates[] = $c['event_date'];
        $calendar_closures[$c['event_date']] = $c;
    }
}

$is_closure_date = in_array($selected_date, $closure_dates, true);
$closure_info = $is_closure_date ? ($calendar_closures[$selected_date] ?? null) : null;

if ($schema_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_register'])) {
    $selected_class = trim($_POST['class_name'] ?? '');
    $selected_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $posted_positive_amount_count = 0;
    if (is_array($_POST['amount'] ?? null)) {
        foreach ($_POST['amount'] as $raw_amount) {
            if ((float)$raw_amount > 0) {
                $posted_positive_amount_count++;
            }
        }
    }

    if ($selected_class === '') {
        $error = 'Select a class before saving.';
    } elseif ($is_closure_date) {
        $closure_label = $closure_info ? ucfirst($closure_info['event_type']) : 'Closure';
        $closure_desc = $closure_info ? htmlspecialchars($closure_info['description']) : '';
        $error = "Cannot mark payments on $closure_label ($closure_desc). This date is marked as an institutional closure in the academic calendar.";
    } elseif ($has_closeouts_table) {
        $lock_stmt = $conn->prepare("SELECT is_locked FROM feeding_day_closeouts WHERE class_name = ? AND close_date = ? AND academic_year = ? AND semester = ? LIMIT 1");
        if ($lock_stmt) {
            $lock_stmt->bind_param('ssss', $selected_class, $selected_date, $acad_year, $current_semester);
            $lock_stmt->execute();
            $lock_row = $lock_stmt->get_result()->fetch_assoc();
            $lock_stmt->close();
            if ($lock_row && (int)$lock_row['is_locked'] === 1) {
                $error = 'This class/date register is locked by a day closeout. Reopen it before saving changes.';
            }
        }
    }

    if (!$error) {
                $stmt = $conn->prepare("SELECT dwf.id as plan_id, dwf.plan_type, dwf.amount_per_unit, s.id as student_id, s.first_name, s.last_name
                                FROM student_daily_weekly_feeding dwf
                                JOIN students s ON s.id = dwf.student_id
                                WHERE dwf.academic_year = ?
                                  AND dwf.semester = ?
                                  AND dwf.status = 'active'
                                  AND dwf.plan_type IN ('weekly','monthly')
                                  AND s.status = 'active'
                                  AND CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                ORDER BY s.first_name, s.last_name");
        $stmt->bind_param('sss', $acad_year, $current_semester, $selected_class);
        $stmt->execute();
        $learners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$learners) {
            $error = 'No weekly/monthly feeding learners were found for this class in the current semester. Legacy daily plans are excluded from marking.';
        }

        $inserted = 0;
        $updated = 0;
        $deleted = 0;
        $adjustment_changes = 0;
        $monthly_validation_errors = 0;
        $overpaid_total = 0;
        $overpaid_rows = [];
        $save_week_meta = feeding_week_interval($conn, $selected_date ?: date('Y-m-d'));
        $save_week_start = $save_week_meta['week_start'] ?? $selected_date;
        $save_week_end = $save_week_meta['week_end'] ?? $selected_date;

        foreach ($learners as $learner) {
            $student_id = (int)$learner['student_id'];
            $plan_id = (int)$learner['plan_id'];
            $plan_type = $learner['plan_type'];
            if (!in_array($plan_type, ['weekly', 'monthly'], true)) {
                continue;
            }
            $amount = (float)($_POST['amount'][$student_id] ?? 0);
            $has_amount = $amount > 0;
            $payment_method = trim($_POST['method'][$student_id] ?? 'cash');
            $notes = trim($_POST['notes'][$student_id] ?? '');
            $month_no = (int)($_POST['month_no'][$student_id] ?? 0);
            $months_count = (int)($_POST['months_count'][$student_id] ?? 1);
            if ($months_count < 1) {
                $months_count = 1;
            }
            $units_paid = $months_count;
            $days_missed = max(0, min(5, (int)($_POST['days_missed'][$student_id] ?? 0)));
            $adjust_reason = trim((string)($_POST['adjust_reason'][$student_id] ?? ''));

            if ($plan_type === 'weekly' && $has_weekly_adjustments_table) {
                $adj_check_stmt = $conn->prepare("SELECT id, days_missed, reason
                                                FROM feeding_weekly_adjustments
                                                WHERE student_id = ?
                                                  AND student_feeding_plan_id = ?
                                                  AND week_start_date = ?
                                                  AND week_end_date = ?
                                                  AND academic_year = ?
                                                  AND semester = ?
                                                LIMIT 1");
                $existing_adjustment = null;
                if ($adj_check_stmt) {
                    $adj_check_stmt->bind_param('iissss', $student_id, $plan_id, $save_week_start, $save_week_end, $acad_year, $current_semester);
                    $adj_check_stmt->execute();
                    $existing_adjustment = $adj_check_stmt->get_result()->fetch_assoc();
                    $adj_check_stmt->close();
                }

                $has_adjustment_data = ($days_missed > 0 || $adjust_reason !== '');
                if ($has_adjustment_data) {
                    if ($existing_adjustment) {
                        $old_days = (int)($existing_adjustment['days_missed'] ?? 0);
                        $old_reason = trim((string)($existing_adjustment['reason'] ?? ''));
                        if ($old_days !== $days_missed || $old_reason !== $adjust_reason) {
                            $adj_update_stmt = $conn->prepare("UPDATE feeding_weekly_adjustments
                                                              SET days_missed = ?, reason = ?, updated_by = ?
                                                              WHERE id = ?");
                            if ($adj_update_stmt) {
                                $user_id = (int)($_SESSION['user_id'] ?? 0);
                                $adj_id = (int)$existing_adjustment['id'];
                                $adj_update_stmt->bind_param('isii', $days_missed, $adjust_reason, $user_id, $adj_id);
                                if ($adj_update_stmt->execute()) {
                                    $adjustment_changes++;
                                }
                                $adj_update_stmt->close();
                            }
                        }
                    } else {
                        $adj_insert_stmt = $conn->prepare("INSERT INTO feeding_weekly_adjustments
                                                          (student_id, student_feeding_plan_id, week_start_date, week_end_date, academic_year, semester, days_missed, reason, created_by, updated_by)
                                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($adj_insert_stmt) {
                            $user_id = (int)($_SESSION['user_id'] ?? 0);
                            $adj_insert_stmt->bind_param('iissssisii', $student_id, $plan_id, $save_week_start, $save_week_end, $acad_year, $current_semester, $days_missed, $adjust_reason, $user_id, $user_id);
                            if ($adj_insert_stmt->execute()) {
                                $adjustment_changes++;
                            }
                            $adj_insert_stmt->close();
                        }
                    }
                } elseif ($existing_adjustment) {
                    $adj_delete_stmt = $conn->prepare("DELETE FROM feeding_weekly_adjustments WHERE id = ?");
                    if ($adj_delete_stmt) {
                        $adj_id = (int)$existing_adjustment['id'];
                        $adj_delete_stmt->bind_param('i', $adj_id);
                        if ($adj_delete_stmt->execute()) {
                            $adjustment_changes++;
                        }
                        $adj_delete_stmt->close();
                    }
                }
            }

            $expected_for_warning = (float)($learner['amount_per_unit'] ?? 0);
            if ($plan_type === 'weekly') {
                $weekly_paid = feeding_week_paid_total($conn, $student_id, $plan_id, $save_week_start, $save_week_end);
                $adjusted_expected_for_week = $expected_for_warning * ((5 - $days_missed) / 5);
                $adjusted_expected_for_week = max(0, $adjusted_expected_for_week);
                $expected_for_warning = max(0, $adjusted_expected_for_week - $weekly_paid);
            } elseif ($plan_type === 'monthly') {
                $expected_for_warning = $expected_for_warning * max(1, $months_count);
            }

            if ($has_amount && $amount > $expected_for_warning) {
                $over = $amount - $expected_for_warning;
                if ($over > 0) {
                    $overpaid_total += $over;
                    if (count($overpaid_rows) < 5) {
                        $overpaid_rows[] = trim((string)($learner['first_name'] ?? '')) . ' ' . trim((string)($learner['last_name'] ?? ''));
                    }
                }
            }

            if ($has_amount && $plan_type === 'monthly') {
                if ($month_no < 1 || $month_no > 12 || $months_count < 1) {
                    $monthly_validation_errors++;
                    continue;
                }
            }

            $check = $conn->prepare("SELECT id FROM feeding_payments WHERE student_id = ? AND payment_date = ? AND payment_type = ? LIMIT 1");
            $check->bind_param('iss', $student_id, $selected_date, $plan_type);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if ($has_amount) {
                if ($existing) {
                    $fields = ["amount = ?", "notes = ?"];
                    $types = 'ds';
                    $values = [$amount, $notes];

                    if ($has_payment_method) {
                        $fields[] = "payment_method = ?";
                        $types .= 's';
                        $values[] = $payment_method;
                    }
                    if ($has_recorded_by) {
                        $fields[] = "recorded_by = ?";
                        $types .= 'i';
                        $values[] = (int)($_SESSION['user_id'] ?? 0);
                    }
                    if ($has_month_no) {
                        $fields[] = "month_no = ?";
                        $types .= 'i';
                        $values[] = ($plan_type === 'monthly' ? $month_no : null);
                    }
                    if ($has_months_count) {
                        $fields[] = "months_count = ?";
                        $types .= 'i';
                        $values[] = ($plan_type === 'monthly' ? $months_count : 1);
                    }
                    if ($has_units_paid) {
                        $fields[] = "units_paid = ?";
                        $types .= 'd';
                        $values[] = (float)$units_paid;
                    }

                    $sql = "UPDATE feeding_payments SET " . implode(', ', $fields) . " WHERE id = ?";
                    $types .= 'i';
                    $values[] = (int)$existing['id'];

                    $stmt_up = $conn->prepare($sql);
                    if ($stmt_up) {
                        feed_bind_params($stmt_up, $types, $values);
                        if ($stmt_up->execute()) {
                            $updated++;
                        }
                        $stmt_up->close();
                    }
                } else {
                    $cols = ['student_id', 'student_feeding_plan_id', 'payment_date', 'payment_type', 'amount', 'notes'];
                    $placeholders = ['?', '?', '?', '?', '?', '?'];
                    $types = 'iissds';
                    $values = [$student_id, $plan_id, $selected_date, $plan_type, $amount, $notes];

                    if ($has_payment_method) {
                        $cols[] = 'payment_method';
                        $placeholders[] = '?';
                        $types .= 's';
                        $values[] = $payment_method;
                    }
                    if ($has_recorded_by) {
                        $cols[] = 'recorded_by';
                        $placeholders[] = '?';
                        $types .= 'i';
                        $values[] = (int)($_SESSION['user_id'] ?? 0);
                    }
                    if ($has_month_no) {
                        $cols[] = 'month_no';
                        $placeholders[] = '?';
                        $types .= 'i';
                        $values[] = ($plan_type === 'monthly' ? $month_no : null);
                    }
                    if ($has_months_count) {
                        $cols[] = 'months_count';
                        $placeholders[] = '?';
                        $types .= 'i';
                        $values[] = ($plan_type === 'monthly' ? $months_count : 1);
                    }
                    if ($has_units_paid) {
                        $cols[] = 'units_paid';
                        $placeholders[] = '?';
                        $types .= 'd';
                        $values[] = (float)$units_paid;
                    }

                    $sql = "INSERT INTO feeding_payments (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt_in = $conn->prepare($sql);
                    if ($stmt_in) {
                        feed_bind_params($stmt_in, $types, $values);
                        if ($stmt_in->execute()) {
                            $inserted++;
                        }
                        $stmt_in->close();
                    }
                }
            } else {
                if ($existing) {
                    $del = $conn->prepare("DELETE FROM feeding_payments WHERE id = ?");
                    $del->bind_param('i', $existing['id']);
                    if ($del->execute()) {
                        $deleted++;
                    }
                    $del->close();
                }
            }
        }

        if (!$error && $monthly_validation_errors > 0) {
            $error = "Register saved with {$monthly_validation_errors} monthly row(s) skipped. For monthly payments, select month and months count.";
        }
        if (!$error && $inserted === 0 && $updated === 0 && $deleted === 0) {
            if ($posted_positive_amount_count > 0) {
                $error = 'No matching weekly/monthly learners were updated from the entered amounts. Please refresh and try again.';
            } elseif ($adjustment_changes > 0) {
                $success = "Weekly adjustments saved for {$adjustment_changes} learner(s).";
            } else {
                $success = 'No changes were saved. Enter an amount greater than 0 for at least one learner, then save.';
            }
        } elseif (!$error) {
            $success = "Register saved. Inserted: $inserted, Updated: $updated, Unmarked removed: $deleted.";
            if ($adjustment_changes > 0) {
                $success .= " Weekly adjustments changed: {$adjustment_changes}.";
            }
        } else {
            $success = "Partial save complete. Inserted: $inserted, Updated: $updated, Unmarked removed: $deleted.";
            if ($adjustment_changes > 0) {
                $success .= " Weekly adjustments changed: {$adjustment_changes}.";
            }
        }

        if ($overpaid_total > 0) {
            $preview_names = implode(', ', array_filter($overpaid_rows));
            $warning = 'Overpayment noticed: GHS ' . number_format($overpaid_total, 2) . ' above expected was recorded.';
            if ($preview_names !== '') {
                $warning .= ' Learners: ' . $preview_names;
                if (count($overpaid_rows) >= 5) {
                    $warning .= '...';
                }
            }
        }
    }
}

$class_options = [];
if ($schema_ready) {
    $stmt = $conn->prepare("SELECT DISTINCT s.class
                            FROM student_daily_weekly_feeding dwf
                            JOIN students s ON s.id = dwf.student_id
                            WHERE dwf.academic_year = ? AND dwf.semester = ? AND dwf.status = 'active' AND dwf.plan_type IN ('weekly','monthly') AND s.status = 'active' AND s.class IS NOT NULL AND s.class != ''
                            ORDER BY s.class");
    $stmt->bind_param('ss', $acad_year, $current_semester);
    $stmt->execute();
    $class_options = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($selected_class === '' && !empty($class_options)) {
        $selected_class = $class_options[0]['class'];
    }
}

$method_select = $has_payment_method ? 'fp.payment_method' : "'cash' AS payment_method";
$month_select = $has_month_no ? 'fp.month_no' : 'NULL AS month_no';
$months_count_select = $has_months_count ? 'fp.months_count' : '1 AS months_count';
$expected_amount_select = 'dwf.amount_per_unit';
if ($has_rates_table) {
        $expected_amount_select = "COALESCE((
                SELECT fcr.amount
                FROM feeding_class_rates fcr
        WHERE CONVERT(fcr.class_name USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci
            AND fcr.plan_type = dwf.plan_type
                    AND fcr.is_active = 1
                    AND fcr.effective_from <= ?
                ORDER BY fcr.effective_from DESC, fcr.id DESC
                LIMIT 1
        ), dwf.amount_per_unit)";
}

$selected_ts = strtotime($selected_date ?: date('Y-m-d'));
if ($selected_ts === false) {
    $selected_ts = strtotime(date('Y-m-d'));
}
// Settings-driven week interval (semester-anchored from system_settings)
$week_info   = feeding_week_interval($conn, $selected_date ?: date('Y-m-d'));
$week_no     = $week_info['week_no'];
$week_start  = $week_info['week_start'];
$week_end    = $week_info['week_end'];
$week_label  = $week_info['label'];
$weeks_total = $week_info['weeks_total'];

$today_date = date('Y-m-d');
$current_week_info = feeding_week_interval($conn, $today_date);
$current_week_label = $current_week_info['label'];

$weekly_adjustments_map = [];
if ($schema_ready && $has_weekly_adjustments_table && $selected_class !== '') {
    $adj_stmt = $conn->prepare("SELECT student_id, student_feeding_plan_id, days_missed, reason
                               FROM feeding_weekly_adjustments
                               WHERE week_start_date = ?
                                 AND week_end_date = ?
                                 AND academic_year = ?
                                 AND semester = ?
                               ORDER BY id DESC");
    if ($adj_stmt) {
        $adj_stmt->bind_param('ssss', $week_start, $week_end, $acad_year, $current_semester);
        $adj_stmt->execute();
        $adj_rows = $adj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $adj_stmt->close();
        foreach ($adj_rows as $adj_row) {
            $adj_key = (int)($adj_row['student_id'] ?? 0) . ':' . (int)($adj_row['student_feeding_plan_id'] ?? 0);
            $weekly_adjustments_map[$adj_key] = [
                'days_missed' => max(0, min(5, (int)($adj_row['days_missed'] ?? 0))),
                'reason' => (string)($adj_row['reason'] ?? ''),
            ];
        }
    }
}

$selected_month_no    = (int)date('n', $selected_ts);
$selected_year_no     = (int)date('Y', $selected_ts);
$selected_month_label = date('F Y', $selected_ts);

$learners = [];
if ($schema_ready && $selected_class !== '') {
    $sql = "SELECT
                s.id,
                s.first_name,
                s.last_name,
                CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
                s.class as class_name,
                dwf.id as plan_id,
                dwf.plan_type,
                $expected_amount_select AS expected_amount,
                dwf.amount_per_unit,
                fp.id as payment_id,
                fp.amount as paid_amount,
                $method_select,
                fp.notes as payment_notes,
                $month_select,
                $months_count_select
            FROM student_daily_weekly_feeding dwf
            JOIN students s ON s.id = dwf.student_id
            LEFT JOIN feeding_payments fp ON fp.student_id = s.id
                AND fp.student_feeding_plan_id = dwf.id
                AND fp.payment_date = ?
                AND fp.payment_type = dwf.plan_type
            WHERE dwf.academic_year = ?
                AND dwf.semester = ?
                AND dwf.status = 'active'
                AND dwf.plan_type IN ('weekly','monthly')
                AND s.status = 'active'
                AND CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
            ORDER BY s.first_name, s.last_name";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($has_rates_table) {
            $stmt->bind_param('sssss', $selected_date, $selected_date, $acad_year, $current_semester, $selected_class);
        } else {
            $stmt->bind_param('ssss', $selected_date, $acad_year, $current_semester, $selected_class);
        }
        $stmt->execute();
        $learners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($learners as &$learner_row) {
            if (($learner_row['plan_type'] ?? '') === 'weekly') {
                $weekly_expected = (float)($learner_row['expected_amount'] ?? 0);
                $adjust_key = (int)$learner_row['id'] . ':' . (int)$learner_row['plan_id'];
                $days_missed = (int)($weekly_adjustments_map[$adjust_key]['days_missed'] ?? 0);
                $adjust_reason = (string)($weekly_adjustments_map[$adjust_key]['reason'] ?? '');
                $days_factor = (5 - max(0, min(5, $days_missed))) / 5;
                $adjusted_expected = max(0, $weekly_expected * $days_factor);
                $weekly_paid = feeding_week_paid_total($conn, (int)$learner_row['id'], (int)$learner_row['plan_id'], $week_start, $week_end);
                $learner_row['carry_in'] = 0;
                $learner_row['weekly_target'] = $weekly_expected;
                $learner_row['weekly_days_missed'] = $days_missed;
                $learner_row['weekly_adjust_reason'] = $adjust_reason;
                $learner_row['weekly_adjusted_target'] = $adjusted_expected;
                $learner_row['weekly_paid_this_week'] = $weekly_paid;
                $learner_row['weekly_remaining'] = max(0, $adjusted_expected - $weekly_paid);
            }
        }
        unset($learner_row);
    }
}

$unpaid_weekly  = [];
$unpaid_monthly = [];
$history_totals  = [
    'weekly'  => 0,
    'monthly' => 0,
    'all'     => 0,
];

// Unpaid tracker — weekly learners whose total paid this week < expected (aggregation-based, supports installments)
if ($schema_ready && $selected_class !== '') {
        // Weekly: join with SUM of payments in the week interval; compute carry-forward in PHP.
        $weekly_sql = "SELECT s.id, s.first_name, s.last_name,
                                                    CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code,
                                                    dwf.id AS plan_id,
                                                    dwf.amount_per_unit AS expected_amount,
                                                    COALESCE(wkp.paid_this_week, 0) AS paid_this_week
                                     FROM student_daily_weekly_feeding dwf
                                     JOIN students s ON s.id = dwf.student_id
                                     LEFT JOIN (
                                             SELECT fp.student_id, fp.student_feeding_plan_id, SUM(fp.amount) AS paid_this_week
                                             FROM feeding_payments fp
                                             WHERE fp.payment_type = 'weekly'
                                                 AND fp.payment_date BETWEEN ? AND ?
                                             GROUP BY fp.student_id, fp.student_feeding_plan_id
                                     ) wkp ON wkp.student_id = s.id AND wkp.student_feeding_plan_id = dwf.id
                                     WHERE dwf.academic_year = ?
                                         AND dwf.semester = ?
                                         AND dwf.status = 'active'
                                         AND dwf.plan_type = 'weekly'
                                         AND s.status = 'active'
                                         AND CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                                     ORDER BY s.first_name, s.last_name";
    $weekly_stmt = $conn->prepare($weekly_sql);
    if ($weekly_stmt) {
        $weekly_stmt->bind_param('sssss', $week_start, $week_end, $acad_year, $current_semester, $selected_class);
        $weekly_stmt->execute();
                $weekly_rows = $weekly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $weekly_stmt->close();

                foreach ($weekly_rows as $weekly_row) {
                    $weekly_expected = (float)($weekly_row['expected_amount'] ?? 0);
                    $adjust_key = (int)$weekly_row['id'] . ':' . (int)$weekly_row['plan_id'];
                    $days_missed = (int)($weekly_adjustments_map[$adjust_key]['days_missed'] ?? 0);
                    $days_factor = (5 - max(0, min(5, $days_missed))) / 5;
                    $adjusted_expected = max(0, $weekly_expected * $days_factor);
                    $weekly_paid = (float)($weekly_row['paid_this_week'] ?? 0);
                    $weekly_remaining = max(0, $adjusted_expected - $weekly_paid);
                    $weekly_row['carry_in'] = 0;
                    $weekly_row['weekly_target'] = $weekly_expected;
                    $weekly_row['weekly_adjusted_target'] = $adjusted_expected;
                    $weekly_row['remaining'] = $weekly_remaining;
                    $weekly_row['payment_status'] = $weekly_remaining <= 0 ? 'paid' : ($weekly_paid > 0 ? 'partial' : 'outstanding');
                    if ($weekly_remaining > 0) {
                        $unpaid_weekly[] = $weekly_row;
                    }
                }
    }

    // Monthly: learner has no payment for the selected month
    if ($has_month_no) {
        $monthly_sql = "SELECT s.id, s.first_name, s.last_name, CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code
                        FROM student_daily_weekly_feeding dwf
                        JOIN students s ON s.id = dwf.student_id
                        WHERE dwf.academic_year = ?
                          AND dwf.semester = ?
                          AND dwf.status = 'active'
                          AND dwf.plan_type = 'monthly'
                          AND s.status = 'active'
                          AND CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                          AND NOT EXISTS (
                              SELECT 1 FROM feeding_payments fp
                              WHERE fp.student_feeding_plan_id = dwf.id
                                AND fp.student_id = s.id
                                AND fp.payment_type = 'monthly'
                                AND (fp.month_no = ? OR (MONTH(fp.payment_date) = ? AND YEAR(fp.payment_date) = ?))
                          )
                        ORDER BY s.first_name, s.last_name";
        $monthly_stmt = $conn->prepare($monthly_sql);
        if ($monthly_stmt) {
            $monthly_stmt->bind_param('sssiii', $acad_year, $current_semester, $selected_class, $selected_month_no, $selected_month_no, $selected_year_no);
            $monthly_stmt->execute();
            $unpaid_monthly = $monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $monthly_stmt->close();
        }
    } else {
        $monthly_sql = "SELECT s.id, s.first_name, s.last_name, CONCAT('SMS-', LPAD(s.id, 3, '0')) AS student_code
                        FROM student_daily_weekly_feeding dwf
                        JOIN students s ON s.id = dwf.student_id
                        WHERE dwf.academic_year = ?
                          AND dwf.semester = ?
                          AND dwf.status = 'active'
                          AND dwf.plan_type = 'monthly'
                          AND s.status = 'active'
                          AND CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                          AND NOT EXISTS (
                              SELECT 1 FROM feeding_payments fp
                              WHERE fp.student_feeding_plan_id = dwf.id
                                AND fp.student_id = s.id
                                AND fp.payment_type = 'monthly'
                                AND MONTH(fp.payment_date) = ? AND YEAR(fp.payment_date) = ?
                          )
                        ORDER BY s.first_name, s.last_name";
        $monthly_stmt = $conn->prepare($monthly_sql);
        if ($monthly_stmt) {
            $monthly_stmt->bind_param('sssii', $acad_year, $current_semester, $selected_class, $selected_month_no, $selected_year_no);
            $monthly_stmt->execute();
            $unpaid_monthly = $monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $monthly_stmt->close();
        }
    }
}

$expected_total = 0;
$marked_total = 0;
$marked_count = 0;
$method_totals = [
    'cash' => 0,
    'momo' => 0,
    'transfer' => 0,
    'check' => 0,
];
foreach ($learners as $l) {
    $line_expected = (float)$l['expected_amount'];
    if (($l['plan_type'] ?? '') === 'weekly') {
        $line_expected = (float)($l['weekly_adjusted_target'] ?? $line_expected);
    }
    $expected_total += $line_expected;
    if (!empty($l['payment_id'])) {
        $marked_count++;
        $line_amount = (float)($l['paid_amount'] ?? 0);
        $marked_total += $line_amount;
        $method = strtolower((string)($l['payment_method'] ?? 'cash'));
        if (!array_key_exists($method, $method_totals)) {
            $method = 'cash';
        }
        $method_totals[$method] += $line_amount;
    }
}
$variance = $marked_total - $expected_total;
$today_collected_amount = 0;
$week_collected_amount = 0;

if ($schema_ready && $selected_class !== '') {
        $stats_stmt = $conn->prepare("SELECT
                                                                        COALESCE(SUM(CASE WHEN fp.payment_date = ? THEN fp.amount ELSE 0 END), 0) AS today_total,
                                                                        COALESCE(SUM(CASE WHEN fp.payment_date BETWEEN ? AND ? THEN fp.amount ELSE 0 END), 0) AS week_total
                                                                    FROM feeding_payments fp
                                                                    JOIN student_daily_weekly_feeding dwf ON dwf.id = fp.student_feeding_plan_id
                                                                    JOIN students s ON s.id = fp.student_id
                                                                    WHERE dwf.academic_year = ?
                                                                        AND dwf.semester = ?
                                                                        AND dwf.plan_type IN ('weekly','monthly')
                                                                        AND fp.payment_type IN ('weekly','monthly')
                                                                        AND CONVERT(s.class USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci");
        if ($stats_stmt) {
                $stats_stmt->bind_param('ssssss', $selected_date, $week_start, $week_end, $acad_year, $current_semester, $selected_class);
                $stats_stmt->execute();
                $stats_row = $stats_stmt->get_result()->fetch_assoc();
                $stats_stmt->close();
                $today_collected_amount = (float)($stats_row['today_total'] ?? 0);
                $week_collected_amount = (float)($stats_row['week_total'] ?? 0);
        }
}

$closeout = null;
$is_register_locked = false;

if ($schema_ready && $has_closeouts_table && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_day'])) {
    $selected_class = trim($_POST['class_name'] ?? '');
    $selected_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $close_notes = trim($_POST['close_notes'] ?? '');
    $expected_total_post = (float)($_POST['expected_total'] ?? 0);
    $collected_total_post = (float)($_POST['collected_total'] ?? 0);
    $variance_post = (float)($_POST['variance'] ?? 0);
    $cash_total_post = (float)($_POST['cash_total'] ?? 0);
    $momo_total_post = (float)($_POST['momo_total'] ?? 0);
    $transfer_total_post = (float)($_POST['transfer_total'] ?? 0);
    $check_total_post = (float)($_POST['check_total'] ?? 0);

    if ($selected_class === '') {
        $error = 'Select a class before running day closeout.';
    } else {
        $sql = "INSERT INTO feeding_day_closeouts (
                    class_name, close_date, academic_year, semester,
                    expected_total, collected_total, variance,
                    cash_total, momo_total, transfer_total, check_total,
                    close_notes, is_locked, closed_by, closed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    expected_total = VALUES(expected_total),
                    collected_total = VALUES(collected_total),
                    variance = VALUES(variance),
                    cash_total = VALUES(cash_total),
                    momo_total = VALUES(momo_total),
                    transfer_total = VALUES(transfer_total),
                    check_total = VALUES(check_total),
                    close_notes = VALUES(close_notes),
                    is_locked = 1,
                    closed_by = VALUES(closed_by),
                    closed_at = NOW()";
        $stmt_close = $conn->prepare($sql);
        if ($stmt_close) {
            $user_id = (int)($_SESSION['user_id'] ?? 0);
            $close_values = [
                $selected_class,
                $selected_date,
                $acad_year,
                $current_semester,
                $expected_total_post,
                $collected_total_post,
                $variance_post,
                $cash_total_post,
                $momo_total_post,
                $transfer_total_post,
                $check_total_post,
                $close_notes,
                $user_id,
            ];
            feed_bind_params($stmt_close, 'ssssdddddddsi', $close_values);
            if ($stmt_close->execute()) {
                $success = 'Day closeout saved and register locked for this class/date.';
            } else {
                $error = 'Could not save day closeout.';
            }
            $stmt_close->close();
        }
    }
}

if ($schema_ready && $has_closeouts_table && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen_day'])) {
    $selected_class = trim($_POST['class_name'] ?? '');
    $selected_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    if ($selected_class === '') {
        $error = 'Select a class before reopening closeout.';
    } else {
        $set_parts = ['is_locked = 0'];
        $types = '';
        $values = [];
        if ($has_closeout_reopened_by) {
            $set_parts[] = 'reopened_by = ?';
            $types .= 'i';
            $values[] = (int)($_SESSION['user_id'] ?? 0);
        }
        if ($has_closeout_reopened_at) {
            $set_parts[] = 'reopened_at = NOW()';
        }

        $reopen_sql = "UPDATE feeding_day_closeouts SET " . implode(', ', $set_parts) . " WHERE class_name = ? AND close_date = ? AND academic_year = ? AND semester = ?";
        $stmt_reopen = $conn->prepare($reopen_sql);
        if ($stmt_reopen) {
            $types .= 'ssss';
            $values[] = $selected_class;
            $values[] = $selected_date;
            $values[] = $acad_year;
            $values[] = $current_semester;
            feed_bind_params($stmt_reopen, $types, $values);

            if ($stmt_reopen->execute()) {
                if ($stmt_reopen->affected_rows > 0) {
                    $success = 'Day closeout reopened. Register is editable again.';
                } else {
                    // Handle no-change updates gracefully (e.g., already open or stale filter context).
                    $check_stmt = $conn->prepare("SELECT is_locked FROM feeding_day_closeouts WHERE class_name = ? AND close_date = ? AND academic_year = ? AND semester = ? LIMIT 1");
                    if ($check_stmt) {
                        $check_stmt->bind_param('ssss', $selected_class, $selected_date, $acad_year, $current_semester);
                        $check_stmt->execute();
                        $check_row = $check_stmt->get_result()->fetch_assoc();
                        $check_stmt->close();

                        if ($check_row && (int)($check_row['is_locked'] ?? 0) === 0) {
                            $success = 'Register is already open for this class/date.';
                        } elseif ($check_row) {
                            $error = 'Reopen did not change the lock state. Please try once more.';
                        } else {
                            $error = 'No closeout record exists for this class/date in the current semester context.';
                        }
                    } else {
                        $error = 'No closeout record was reopened for this class/date.';
                    }
                }
            } else {
                $error = 'Could not reopen closeout. Please verify closeout schema columns and retry.';
            }
            $stmt_reopen->close();
        }
    }
}

if ($schema_ready && $has_closeouts_table && $selected_class !== '') {
    $close_stmt = $conn->prepare("SELECT * FROM feeding_day_closeouts WHERE class_name = ? AND close_date = ? AND academic_year = ? AND semester = ? LIMIT 1");
    if ($close_stmt) {
        $close_stmt->bind_param('ssss', $selected_class, $selected_date, $acad_year, $current_semester);
        $close_stmt->execute();
        $closeout = $close_stmt->get_result()->fetch_assoc();
        $close_stmt->close();
    }
}
$is_register_locked = $closeout && (int)($closeout['is_locked'] ?? 0) === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Marking Register | Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-emerald-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="dashboard.php" class="hover:text-emerald-600 transition-colors">Feeding Dashboard</a>
                <span>/</span>
                <span class="text-emerald-600">Marking Register</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-list-check text-orange-600"></i> Feeding Marking Register
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Attendance-style live marking by class and date.</p>
                    <div class="mt-2 inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-semibold">
                        <i class="fas fa-calendar-week"></i>
                        <span>Current Week: <?= htmlspecialchars($current_week_label) ?></span>
                    </div>
                </div>
                <div class="flex flex-col items-stretch md:items-end gap-2 w-full md:w-auto">
                    <div class="flex flex-wrap gap-2">
                        <a href="registered_learners.php" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Registered Learners</a>
                        <a href="daily_collection_sheet.php?class=<?= urlencode($selected_class) ?>&date=<?= urlencode($selected_date) ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Print Sheet</a>
                        <a href="feeding_summary_report.php" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Summary</a>
                    </div>
                    <details class="w-full md:w-auto">
                        <summary class="list-none cursor-pointer px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 text-center">
                            More Actions
                        </summary>
                        <div class="mt-2 flex flex-wrap gap-2 md:justify-end">
                            <a href="enroll_daily_feeding.php" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Enroll Student</a>
                            <a href="#closeout" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Day Closeout</a>
                            <a href="closeout_history.php" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Closeout History</a>
                            <a href="feeding_settings.php" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Settings</a>
                            <a href="record_feeding_payment.php" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Single Payment</a>
                            <a href="feeding_summary_report.php#switch-back" class="px-3 py-2 border border-emerald-300 rounded-lg text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Restore To Bill</a>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-semibold">
                Feeding tables are not ready. Run the patch script, then refresh this page.
            </div>
        <?php endif; ?>

        <?php if ($is_closure_date && $closure_info): ?>
            <div class="mb-6 bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-xl text-sm font-semibold flex items-start gap-3">
                <i class="fas fa-ban text-lg mt-0.5"></i>
                <div>
                    <p class="font-black uppercase tracking-wider"><?= ucfirst(htmlspecialchars($closure_info['event_type'])) ?> · No Marking Allowed</p>
                    <p class="text-rose-700 mt-1"><?= htmlspecialchars($closure_info['description']) ?></p>
                    <p class="text-xs text-rose-600 mt-1.5">All entry fields are disabled. Please select a different date to mark payments.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-semibold">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-semibold">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($warning): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-xl text-sm font-semibold">
                <?= htmlspecialchars($warning) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl border border-slate-200 px-3 sm:px-4 py-3 mb-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:gap-4">
                <!-- Filters Section -->
                <form method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-3 md:gap-2">
                    <div class="flex-1 sm:flex-none min-w-[115px]">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Class</label>
                        <select name="class" class="w-full px-2 py-2 sm:py-1.5 border border-slate-300 rounded-lg text-xs focus:border-indigo-500 focus:outline-none" onchange="this.form.submit()">
                            <option value="">Select class</option>
                            <?php foreach ($class_options as $option): ?>
                                <option value="<?= htmlspecialchars($option['class']) ?>" <?= $selected_class === $option['class'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($option['class']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 sm:flex-none min-w-[115px]">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-0.5">Date</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" class="w-full px-2 py-2 sm:py-1.5 border border-slate-300 rounded-lg text-xs focus:border-indigo-500 focus:outline-none" onchange="this.form.submit()">
                    </div>
                    <div class="flex-1 sm:flex-none">
                        <a href="daily_weekly_tracker.php" class="w-full sm:w-auto inline-flex items-center justify-center px-3 py-2 sm:py-1.5 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">Reset</a>
                    </div>
                </form>

                <!-- Spacer -->
                <div class="hidden md:flex flex-grow"></div>

                <!-- Actions Section -->
                <div class="flex gap-2 flex-shrink-0 md:items-end">
                    <?php if (!$has_closeouts_table): ?>
                        <span class="text-xs text-amber-700 font-semibold px-3 py-1.5">—</span>
                    <?php elseif ($selected_class === ''): ?>
                        <span class="text-xs text-slate-500 font-semibold px-3 py-1.5">—</span>
                    <?php else: ?>
                        <?php if ($is_register_locked && $closeout): ?>
                            <form method="POST" class="w-full sm:w-auto">
                                <input type="hidden" name="reopen_day" value="1">
                                <input type="hidden" name="class_name" value="<?= htmlspecialchars($selected_class) ?>">
                                <input type="hidden" name="payment_date" value="<?= htmlspecialchars($selected_date) ?>">
                                <button type="submit" class="w-full px-4 py-2 text-xs font-bold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition">Open</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="w-full sm:w-auto">
                                <input type="hidden" name="close_day" value="1">
                                <input type="hidden" name="class_name" value="<?= htmlspecialchars($selected_class) ?>">
                                <input type="hidden" name="payment_date" value="<?= htmlspecialchars($selected_date) ?>">
                                <input type="hidden" name="expected_total" value="<?= htmlspecialchars((string)$expected_total) ?>">
                                <input type="hidden" name="collected_total" value="<?= htmlspecialchars((string)$marked_total) ?>">
                                <input type="hidden" name="variance" value="<?= htmlspecialchars((string)$variance) ?>">
                                <input type="hidden" name="cash_total" value="<?= htmlspecialchars((string)$method_totals['cash']) ?>">
                                <input type="hidden" name="momo_total" value="<?= htmlspecialchars((string)$method_totals['momo']) ?>">
                                <input type="hidden" name="transfer_total" value="<?= htmlspecialchars((string)$method_totals['transfer']) ?>">
                                <input type="hidden" name="check_total" value="<?= htmlspecialchars((string)$method_totals['check']) ?>">
                                <input type="hidden" name="close_notes" value="">
                                <button type="submit" class="w-full px-4 py-2 text-xs font-bold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">Close</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3 border-t border-slate-200 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                    <p class="text-[9px] font-bold text-emerald-700 uppercase tracking-widest">Day Received</p>
                    <p class="text-[1.1rem] font-black text-emerald-700 leading-none mt-0.5">GHS <?= number_format($today_collected_amount, 2) ?></p>
                </div>
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2">
                    <p class="text-[9px] font-bold text-indigo-700 uppercase tracking-widest">Week Collected</p>
                    <p class="text-[1.1rem] font-black text-indigo-700 leading-none mt-0.5">GHS <?= number_format($week_collected_amount, 2) ?></p>
                </div>
            </div>
        </div>

        <form method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <input type="hidden" name="save_register" value="1">
            <input type="hidden" name="class_name" value="<?= htmlspecialchars($selected_class) ?>">
            <input type="hidden" name="payment_date" value="<?= htmlspecialchars($selected_date) ?>">

            <div class="px-4 py-3 border-b border-slate-200 bg-white sticky top-0 z-20 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Class Register</h2>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg <?= ($is_register_locked || $is_closure_date) ? 'bg-slate-300 text-slate-600 cursor-not-allowed' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>" <?= ($is_register_locked || $is_closure_date) ? 'disabled' : '' ?>>Save Register</button>
                </div>
            </div>

            <?php if ($is_register_locked): ?>
                <div class="mx-4 mt-4 mb-0 px-3 py-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
                    This register is locked by day closeout for <?= htmlspecialchars($selected_class) ?> on <?= htmlspecialchars($selected_date) ?>. Reopen it from the closeout section to edit.
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px]">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Learner</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Plan</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Expected</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Amount Paid</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Adj Days</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Adj Reason</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Month</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Months</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">History</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$learners): ?>
                            <tr>
                                <td colspan="11" class="px-4 py-10 text-center text-sm text-slate-500">No active feeding learners found for this class/date context.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($learners as $row): ?>
                                <?php
                                    $sid = (int)$row['id'];
                                    $is_paid = !empty($row['payment_id']);
                                    $default_amount = (float)$row['expected_amount'];
                                    $existing_paid_amount = $is_paid ? (float)$row['paid_amount'] : 0;
                                    $method = $row['payment_method'] ?? 'cash';
                                    $month_no = (int)($row['month_no'] ?? 0);
                                    $months_count = (int)($row['months_count'] ?? 1);
                                    $weekly_days_missed = (int)($row['weekly_days_missed'] ?? 0);
                                    $weekly_adjust_reason = (string)($row['weekly_adjust_reason'] ?? '');
                                    $weekly_adjusted_target = (float)($row['weekly_adjusted_target'] ?? $default_amount);
                                    $weekly_remaining = (float)($row['weekly_remaining'] ?? 0);
                                    $due_amount = $default_amount;
                                    if ($row['plan_type'] === 'weekly') {
                                        $due_amount = $weekly_remaining;
                                    } elseif ($row['plan_type'] === 'monthly') {
                                        $due_amount = $default_amount * max(1, $months_count);
                                    }
                                    $display_amount = $is_paid ? $existing_paid_amount : max(0, $due_amount);
                                ?>
                                <tr class="register-row hover:bg-slate-50 <?= $is_closure_date ? 'opacity-60 bg-gray-100/40' : '' ?>">
                                    <td class="px-4 py-3">
                                        <a href="student_history.php?student_id=<?= $sid ?>&class=<?= urlencode($selected_class) ?>&date=<?= urlencode($selected_date) ?>&history_type=all" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                        </a>
                                        <p class="text-xs text-slate-500"><?= htmlspecialchars($row['student_code']) ?> · <?= htmlspecialchars($row['class_name']) ?></p>
                                        <?php if ($is_closure_date): ?>
                                            <p class="text-xs text-rose-600 font-semibold mt-1"><i class="fas fa-ban"></i> Closure Date</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700"><?= ucfirst(htmlspecialchars($row['plan_type'])) ?></td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                                        GHS <?= number_format($default_amount, 2) ?>
                                        <?php if ($row['plan_type'] === 'weekly'): ?>
                                            <div class="text-[10px] text-indigo-600 mt-1">
                                                Adjusted target: GHS <span class="adjusted-target-value" data-student-id="<?= $sid ?>"><?= number_format($weekly_adjusted_target, 2) ?></span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 mt-1">
                                                Week remaining: GHS <span class="weekly-remaining-value" data-student-id="<?= $sid ?>"><?= number_format($weekly_remaining, 2) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="amount[<?= $sid ?>]"
                                            value="<?= htmlspecialchars(number_format($display_amount, 2, '.', '')) ?>"
                                            class="amount-input w-28 px-2 py-1.5 border border-slate-300 rounded text-sm"
                                            data-expected="<?= htmlspecialchars(number_format($default_amount, 2, '.', '')) ?>"
                                            data-due="<?= htmlspecialchars(number_format($due_amount, 2, '.', '')) ?>"
                                            data-has-payment="<?= $is_paid ? '1' : '0' ?>"
                                            data-plan="<?= htmlspecialchars($row['plan_type']) ?>"
                                            data-weekly-paid="<?= htmlspecialchars(number_format((float)($row['weekly_paid_this_week'] ?? 0), 2, '.', '')) ?>"
                                            data-student-id="<?= $sid ?>"
                                            <?= ($is_register_locked || $is_closure_date) ? 'disabled' : '' ?>>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" min="0" max="5" name="days_missed[<?= $sid ?>]" value="<?= $row['plan_type'] === 'weekly' ? $weekly_days_missed : 0 ?>" class="days-missed-input w-20 px-2 py-1.5 border border-slate-300 rounded text-sm <?= $row['plan_type'] === 'weekly' ? '' : 'opacity-50' ?>" data-student-id="<?= $sid ?>" <?= ($is_register_locked || $is_closure_date || $row['plan_type'] !== 'weekly') ? 'disabled' : '' ?>>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="adjust_reason[<?= $sid ?>]" value="<?= htmlspecialchars($row['plan_type'] === 'weekly' ? $weekly_adjust_reason : '') ?>" class="w-full min-w-[160px] px-2 py-1.5 border border-slate-300 rounded text-sm <?= $row['plan_type'] === 'weekly' ? '' : 'opacity-50' ?>" placeholder="e.g. sick 2 days" <?= ($is_register_locked || $is_closure_date || $row['plan_type'] !== 'weekly') ? 'disabled' : '' ?>>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select name="method[<?= $sid ?>]" class="px-2 py-1.5 border border-slate-300 rounded text-sm" <?= ($is_register_locked || $is_closure_date) ? 'disabled' : '' ?>>
                                            <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>>Cash</option>
                                            <option value="momo" <?= $method === 'momo' ? 'selected' : '' ?>>MoMo</option>
                                            <option value="transfer" <?= $method === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                                            <option value="check" <?= $method === 'check' ? 'selected' : '' ?>>Check</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select name="month_no[<?= $sid ?>]" class="month-select px-2 py-1.5 border border-slate-300 rounded text-sm <?= $row['plan_type'] === 'monthly' ? '' : 'opacity-50' ?>" data-plan="<?= htmlspecialchars($row['plan_type']) ?>" data-student-id="<?= $sid ?>" <?= ($is_register_locked || $is_closure_date || $row['plan_type'] !== 'monthly') ? 'disabled' : '' ?>>
                                            <option value="0">-</option>
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?= $m ?>" <?= $month_no === $m ? 'selected' : '' ?>><?= date('M', mktime(0, 0, 0, $m, 1)) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" min="1" max="12" name="months_count[<?= $sid ?>]" value="<?= $months_count > 0 ? $months_count : 1 ?>" class="months-input w-20 px-2 py-1.5 border border-slate-300 rounded text-sm <?= $row['plan_type'] === 'monthly' ? '' : 'opacity-50' ?>" data-plan="<?= htmlspecialchars($row['plan_type']) ?>" data-student-id="<?= $sid ?>" <?= ($is_register_locked || $is_closure_date || $row['plan_type'] !== 'monthly') ? 'disabled' : '' ?>>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="notes[<?= $sid ?>]" value="<?= htmlspecialchars($row['payment_notes'] ?? '') ?>" class="w-full min-w-[180px] px-2 py-1.5 border border-slate-300 rounded text-sm" placeholder="Optional note" <?= ($is_register_locked || $is_closure_date) ? 'disabled' : '' ?>>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="student_history.php?student_id=<?= $sid ?>&class=<?= urlencode($selected_class) ?>&date=<?= urlencode($selected_date) ?>&history_type=all" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 text-xs font-semibold hover:bg-emerald-50">
                                            <i class="fas fa-clock-rotate-left"></i> View History
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </main>

    <script>
        function formatMoney(value) {
            const num = Number(value);
            if (!Number.isFinite(num) || num < 0) {
                return '0.00';
            }
            return num.toFixed(2);
        }

        function recalcStudentAmount(studentId, force = false) {
            const amountInput = document.querySelector('.amount-input[data-student-id="' + studentId + '"]');
            if (!amountInput) {
                return;
            }

            const expected = Number(amountInput.dataset.expected || 0);
            const due = Number(amountInput.dataset.due || expected);
            const hasPayment = amountInput.dataset.hasPayment === '1';
            const plan = String(amountInput.dataset.plan || '').toLowerCase();
            const weeklyPaid = Number(amountInput.dataset.weeklyPaid || 0);
            const monthsInput = document.querySelector('.months-input[data-student-id="' + studentId + '"]');
            const daysMissedInput = document.querySelector('.days-missed-input[data-student-id="' + studentId + '"]');
            const adjustedTargetEl = document.querySelector('.adjusted-target-value[data-student-id="' + studentId + '"]');
            const weeklyRemainingEl = document.querySelector('.weekly-remaining-value[data-student-id="' + studentId + '"]');
            const current = Number(amountInput.value || 0);

            if (plan === 'monthly') {
                const monthsCount = monthsInput ? Math.max(1, Number(monthsInput.value || 1)) : 1;
                const monthlyDue = expected * monthsCount;
                if (!hasPayment && (force || !Number.isFinite(current) || current <= 0)) {
                    amountInput.value = formatMoney(monthlyDue);
                }
                return;
            }

            if (plan === 'weekly') {
                const daysMissed = daysMissedInput ? Math.max(0, Math.min(5, Number(daysMissedInput.value || 0))) : 0;
                if (daysMissedInput) {
                    daysMissedInput.value = String(daysMissed);
                }
                const adjustedTarget = Math.max(0, expected * ((5 - daysMissed) / 5));
                const weeklyDue = Math.max(0, adjustedTarget - weeklyPaid);

                if (adjustedTargetEl) {
                    adjustedTargetEl.textContent = formatMoney(adjustedTarget);
                }
                if (weeklyRemainingEl) {
                    weeklyRemainingEl.textContent = formatMoney(weeklyDue);
                }

                if (!hasPayment && (force || !Number.isFinite(current) || current <= 0)) {
                    amountInput.value = formatMoney(weeklyDue);
                }
                return;
            }

            if (!hasPayment && (force || !Number.isFinite(current) || current <= 0)) {
                amountInput.value = formatMoney(due);
            }
        }

        function recalcAllRows() {
            document.querySelectorAll('.amount-input').forEach((input) => {
                const studentId = input.dataset.studentId;
                if (studentId) {
                    recalcStudentAmount(studentId, false);
                }
            });
        }

        document.querySelectorAll('.months-input').forEach((input) => {
            input.addEventListener('input', function () {
                const sid = this.dataset.studentId;
                if (sid) {
                    recalcStudentAmount(sid, true);
                }
            });
        });

        document.querySelectorAll('.days-missed-input').forEach((input) => {
            input.addEventListener('input', function () {
                const sid = this.dataset.studentId;
                if (sid) {
                    recalcStudentAmount(sid, true);
                }
            });
        });

        document.addEventListener('DOMContentLoaded', recalcAllRows);
    </script>
</body>
</html>
