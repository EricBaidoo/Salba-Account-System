<?php
/**
 * Shared feeding module helper functions.
 * Safe to include from any page — no auth checks, no DB queries at include time.
 * Requires $conn to be in scope when the functions are called.
 *
 * Functions provided:
 *   feeding_days_in_month($date_string, $conn_ref)
 *   feeding_week_interval($conn_ref, $date_string)
 *   feeding_week_paid_total($conn_ref, $student_id, $plan_id, $week_start, $week_end)
 *   feeding_week_carry_forward($conn_ref, $student_id, $plan_id, $weekly_expected, $week_start)
 */

if (!function_exists('feeding_days_in_month')) {
/**
 * Count Mon-Fri school days in the month of $date_string,
 * excluding holiday/break/mid-term dates in academic_calendar.
 */
function feeding_days_in_month(string $date_string = '', $conn_ref = null): int {
    if ($date_string === '') {
        $date_string = date('Y-m-d');
    }
    $timestamp = strtotime($date_string);
    if ($timestamp === false) {
        return 20;
    }

    $holidays = [];
    if ($conn_ref) {
        $y = date('Y', $timestamp);
        $m = date('m', $timestamp);
        $cal_res = $conn_ref->query(
            "SELECT event_date FROM academic_calendar
             WHERE event_type IN ('holiday','break','mid-term')
               AND YEAR(event_date) = $y AND MONTH(event_date) = $m"
        );
        if ($cal_res) {
            while ($row = $cal_res->fetch_assoc()) {
                $holidays[] = $row['event_date'];
            }
        }
    }

    $first_day = strtotime(date('Y-m-01', $timestamp));
    $last_day  = strtotime(date('Y-m-t',  $timestamp));
    $school_days = 0;

    for ($day = $first_day; $day <= $last_day; $day = strtotime('+1 day', $day)) {
        $weekday  = (int)date('N', $day); // 1=Mon, 7=Sun
        $date_str = date('Y-m-d', $day);
        if ($weekday <= 5 && !in_array($date_str, $holidays, true)) {
            $school_days++;
        }
    }

    return $school_days > 0 ? $school_days : 20;
}
}

if (!function_exists('feeding_week_interval')) {
/**
 * Return the semester-anchored week interval for a given date using system_settings.
 *
 * Returns:
 *   [
 *     'week_no'     => int   (1-based, capped to weeks_per_semester)
 *     'week_start'  => 'Y-m-d'  (Monday of that week)
 *     'week_end'    => 'Y-m-d'  (Friday of that week, clamped to semester end)
 *     'weeks_total' => int
 *     'label'       => string   e.g. "Week 3  (Mon 30 Jun – Fri 4 Jul)"
 *   ]
 *
 * Falls back to plain Monday-Friday if semester_start_date is not configured.
 */
function feeding_week_interval($conn_ref, string $date_string = ''): array {
    if ($date_string === '') {
        $date_string = date('Y-m-d');
    }
    $ts = strtotime($date_string);
    if ($ts === false) {
        $ts = time();
    }

    $semester_start = getSystemSetting($conn_ref, 'semester_start_date', '');
    $semester_end   = getSystemSetting($conn_ref, 'semester_end_date',   '');
    $weeks_total    = max(1, (int)getSystemSetting($conn_ref, 'weeks_per_semester', 12));

    if ($semester_start) {
        $start_dt  = new DateTime($semester_start);
        $start_dt->modify('monday this week');

        $target_dt = new DateTime($date_string);
        $target_dt->modify('monday this week');

        $interval = $start_dt->diff($target_dt);
        if ($interval->invert) {
            $week_no = 1;
        } else {
            $week_no = (int)floor($interval->days / 7) + 1;
            $week_no = max(1, min($week_no, $weeks_total));
        }

        $week_start_dt = clone $target_dt;
        $week_end_dt   = clone $target_dt;
        $week_end_dt->modify('+4 days'); // Mon → Fri

        if ($semester_end) {
            $end_dt = new DateTime($semester_end);
            if ($week_end_dt > $end_dt) {
                $week_end_dt = clone $end_dt;
            }
        }

        $ws = $week_start_dt->format('Y-m-d');
        $we = $week_end_dt->format('Y-m-d');
        $label = sprintf(
            'Week %d  (%s – %s)',
            $week_no,
            $week_start_dt->format('D j M'),
            $week_end_dt->format('D j M')
        );

        return [
            'week_no'    => $week_no,
            'week_start' => $ws,
            'week_end'   => $we,
            'weeks_total'=> $weeks_total,
            'label'      => $label,
        ];
    }

    // Fallback: plain Monday-Friday
    $monday = date('Y-m-d', strtotime('monday this week', $ts));
    $friday = date('Y-m-d', strtotime($monday . ' +4 days'));
    $label  = sprintf(
        'Week (Mon %s – Fri %s)',
        date('j M', strtotime($monday)),
        date('j M', strtotime($friday))
    );

    return [
        'week_no'    => 1,
        'week_start' => $monday,
        'week_end'   => $friday,
        'weeks_total'=> $weeks_total,
        'label'      => $label,
    ];
}
}

if (!function_exists('feeding_week_paid_total')) {
/**
 * Sum of all weekly feeding payments for a learner within a week interval.
 * Supports multiple partial payments in the same week.
 */
function feeding_week_paid_total($conn_ref, int $student_id, int $plan_id, string $week_start, string $week_end): float {
    $stmt = $conn_ref->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM feeding_payments
         WHERE student_id = ?
           AND student_feeding_plan_id = ?
           AND payment_type = 'weekly'
           AND payment_date BETWEEN ? AND ?"
    );
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('iiss', $student_id, $plan_id, $week_start, $week_end);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}
}

if (!function_exists('feeding_week_carry_forward')) {
/**
 * Calculate carry-forward balance into the current week for a learner.
 *
 * carry_in = max(0, SUM(expected_before_week) - SUM(paid_before_week))
 *
 * This iterates over complete weeks from semester_start up to but not including
 * the current week_start. Expensive if called for many learners; consider caching
 * per-page load at the caller.
 *
 * Returns float carry_in amount (>= 0).
 */
function feeding_week_carry_forward($conn_ref, int $student_id, int $plan_id, float $weekly_expected, string $week_start): float {
    $semester_start = getSystemSetting($conn_ref, 'semester_start_date', '');
    if (!$semester_start || $semester_start >= $week_start) {
        return 0.0;
    }

    // Total paid in all weeks BEFORE current week_start
    $stmt = $conn_ref->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total_paid
         FROM feeding_payments
         WHERE student_id = ?
           AND student_feeding_plan_id = ?
           AND payment_type = 'weekly'
           AND payment_date >= ?
           AND payment_date < ?"
    );
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('iiss', $student_id, $plan_id, $semester_start, $week_start);
    $stmt->execute();
    $paid_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $total_paid_before = (float)($paid_row['total_paid'] ?? 0);

    // Count weeks elapsed before current week
    $start_dt  = new DateTime($semester_start);
    $start_dt->modify('monday this week');
    $cur_dt    = new DateTime($week_start);
    $interval  = $start_dt->diff($cur_dt);
    $weeks_elapsed = max(0, (int)floor($interval->days / 7));

    $total_expected_before = $weekly_expected * $weeks_elapsed;
    return max(0.0, $total_expected_before - $total_paid_before);
}
}
