<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/term_helpers.php';
require_once __DIR__ . '/system_settings.php';

function getStudentBalance($conn, $student_id, $term = null, $academic_year = null) {
    $params = [];
    $param_types = "";
    
    // Build term filtering
    if ($term !== null) {
        if ($academic_year === null) {
            $academic_year = getAcademicYear($conn);
        }

        $fee_filter = "AND ((term = ? AND (academic_year = ? OR academic_year IS NULL)) OR term IS NULL)";
        $payment_subquery = "(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = s.id AND ((term = ? AND (academic_year = ? OR academic_year IS NULL)) OR term IS NULL))";
        // Arrears = unpaid from the immediate previous term/year only
        [$prev_term, $prev_year] = getPreviousTermYear($term, $academic_year);
        $arrears_subquery = "
            COALESCE((
                SELECT SUM(sf2.amount - sf2.amount_paid)
                FROM student_fees sf2
                WHERE sf2.student_id = s.id
                  AND sf2.term = ?
                  AND (sf2.academic_year = ? OR sf2.academic_year IS NULL)
                  AND sf2.status != 'cancelled'
                  AND (sf2.amount - sf2.amount_paid) > 0
            ), 0)";
    } else {
        $fee_filter = "";
        $payment_subquery = "(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = s.id)";
        $arrears_subquery = "0";
    }
    
    $sql = "
    SELECT 
        s.id as student_id,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.class,
        s.status as student_status,
        COALESCE(
            (SELECT SUM(amount) FROM student_fees WHERE student_id = s.id $fee_filter), 
        0) as total_fees,
        $payment_subquery as total_payments,
        $arrears_subquery as arrears,
        (SELECT COUNT(*) FROM student_fees WHERE student_id = s.id AND status = 'pending' $fee_filter) as pending_assignments,
        (SELECT COUNT(*) FROM student_fees WHERE student_id = s.id AND status = 'paid' $fee_filter) as paid_assignments
    FROM students s
    WHERE s.id = ?
    ";
    
    // Bind parameters in the order they appear in SQL
    if ($term !== null) {
        $params[] = $term; // For total_fees subquery (term)
        $params[] = $academic_year; // For total_fees subquery (academic_year)
        $params[] = $term; // For payment_subquery (term)
        $params[] = $academic_year; // For payment_subquery (academic_year)
        $params[] = $prev_term; // For arrears_subquery (term)
        $params[] = $prev_year; // For arrears_subquery (academic_year)
        $params[] = $term; // For pending_assignments subquery (term)
        $params[] = $academic_year; // For pending_assignments subquery (academic_year)
        $params[] = $term; // For paid_assignments subquery (term)
        $params[] = $academic_year; // For paid_assignments subquery (academic_year)
        $param_types .= "ssssssssss";
    }
    
    $params[] = $student_id; // For WHERE clause
    $param_types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $balance = $result->fetch_assoc();
    $stmt->close();
    
    if ($balance) {
        if ($term !== null) {
            // Override arrears with snapshot (payments recorded in previous term/year)
            $balance['arrears'] = getArrearsFromPreviousTerm($conn, $student_id, $term, $academic_year);
        }
        // Arrears is kept separately; total_fees already includes current-term assignments (including carry-forward when ensured)
        $balance['arrears'] = max(0, $balance['arrears']);
        // Calculate net balance for current term
        $balance['outstanding_fees'] = max(0, $balance['total_fees'] - $balance['total_payments']);
        $balance['net_balance'] = $balance['outstanding_fees'];
    }
    
    return $balance;
}

function getAllStudentBalances($conn, $class_filter = null, $status_filter = 'active', $term = null, $academic_year = null) {
    $params = [];
    $param_types = "";
    
    // Build term filtering first
    if ($term !== null) {
        if ($academic_year === null) {
            $academic_year = getAcademicYear($conn);
        }

        $fee_filter = "AND ((term = ? AND (academic_year = ? OR academic_year IS NULL)) OR term IS NULL)";
        $payment_subquery = "
            (SELECT COALESCE(SUM(amount), 0) 
             FROM payments 
             WHERE student_id = s.id 
             AND ((term = ? AND (academic_year = ? OR academic_year IS NULL)) OR term IS NULL))";
        // Arrears = unpaid from the immediate previous term/year only
        [$prev_term, $prev_year] = getPreviousTermYear($term, $academic_year);
        $arrears_subquery = "
            COALESCE((
                SELECT SUM(sf2.amount - sf2.amount_paid)
                FROM student_fees sf2
                WHERE sf2.student_id = s.id
                  AND sf2.term = ?
                  AND (sf2.academic_year = ? OR sf2.academic_year IS NULL)
                  AND sf2.status != 'cancelled'
                  AND (sf2.amount - sf2.amount_paid) > 0
            ), 0)";
    } else {
        $fee_filter = "";
        $payment_subquery = "
            (SELECT COALESCE(SUM(amount), 0) 
             FROM payments 
             WHERE student_id = s.id)";
        $arrears_subquery = "0";
    }
    
    $sql = "
    SELECT 
        s.id as student_id,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.class,
        s.status as student_status,
        COALESCE(
            (SELECT SUM(amount) 
             FROM student_fees 
             WHERE student_id = s.id $fee_filter), 
        0) as total_fees,
        $payment_subquery as total_payments,
        $arrears_subquery as arrears,
        (SELECT COUNT(*) 
         FROM student_fees 
         WHERE student_id = s.id 
         AND status = 'pending' $fee_filter) as pending_assignments,
        (SELECT COUNT(*) 
         FROM student_fees 
         WHERE student_id = s.id 
         AND status = 'paid' $fee_filter) as paid_assignments
    FROM students s";
    
    // Build WHERE conditions
    $where_conditions = [];
    
    if ($status_filter && $status_filter !== 'all') {
        $where_conditions[] = "s.status = ?";
    }
    
    if ($class_filter && $class_filter !== 'all') {
        $where_conditions[] = "s.class = ?";
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY s.last_name, s.first_name";
    
    // Now bind parameters in the order they appear in SQL
    if ($term !== null) {
        $params[] = $term; // For total_fees subquery (term)
        $params[] = $academic_year; // For total_fees subquery (academic_year)
        $params[] = $term; // For payment_subquery (term)
        $params[] = $academic_year; // For payment_subquery (academic_year)
        $params[] = $prev_term; // For arrears_subquery (term)
        $params[] = $prev_year; // For arrears_subquery (academic_year)
        $params[] = $term; // For pending_assignments subquery (term)
        $params[] = $academic_year; // For pending_assignments subquery (academic_year)
        $params[] = $term; // For paid_assignments subquery (term)
        $params[] = $academic_year; // For paid_assignments subquery (academic_year)
        $param_types .= "ssssssssss";
    }
    
    if ($status_filter && $status_filter !== 'all') {
        $params[] = $status_filter;
        $param_types .= "s";
    }
    
    if ($class_filter && $class_filter !== 'all') {
        $params[] = $class_filter;
        $param_types .= "s";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balances = [];
    while ($row = $result->fetch_assoc()) {
        // Arrears separate; total_fees is current-term only and should include carry-forward if ensured
        if ($term !== null) {
            $row['arrears'] = getArrearsFromPreviousTerm($conn, $row['student_id'], $term, $academic_year);
        }
        $row['arrears'] = max(0, $row['arrears']);
        // Calculate net balance
        $row['outstanding_fees'] = max(0, $row['total_fees'] - $row['total_payments']);
        $row['net_balance'] = $row['outstanding_fees'];
        $balances[] = $row;
    }
    
    $stmt->close();
    
    return $balances;
}

function getStudentOutstandingFees($conn, $student_id, $term = null, $academic_year = null) {
    $where_conditions = ["sf.student_id = ?", "sf.status = 'pending'"];
    $params = [$student_id];
    $param_types = "i";
    
    if ($term !== null) {
        if ($academic_year === null) {
            $academic_year = getAcademicYear($conn);
        }
        $where_conditions[] = "sf.term = ?";
        $where_conditions[] = "(sf.academic_year = ? OR sf.academic_year IS NULL)";
        $params[] = $term;
        $params[] = $academic_year;
        $param_types .= "ss";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $sql = "
    SELECT 
        sf.id,
        f.name as fee_name,
        sf.amount,
        sf.due_date,
        sf.term,
        sf.assigned_date,
        sf.notes,
        DATEDIFF(sf.due_date, CURDATE()) as days_to_due,
        CASE 
            WHEN sf.due_date < CURDATE() THEN 'Overdue'
            WHEN DATEDIFF(sf.due_date, CURDATE()) <= 7 THEN 'Due Soon'
            ELSE 'Pending'
        END as payment_status
    FROM student_fees sf
    JOIN fees f ON sf.fee_id = f.id
    $where_clause
    ORDER BY sf.due_date ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fees = [];
    while ($row = $result->fetch_assoc()) {
        $fees[] = $row;
    }
    
    $stmt->close();
    return $fees;
}

function getStudentPaymentHistory($conn, $student_id, $term = null, $academic_year = null) {
    $where_conditions = ["p.student_id = ?"];
    $params = [$student_id];
    $param_types = "i";
    
    if ($term !== null) {
        if ($academic_year === null) {
            $academic_year = getAcademicYear($conn);
        }
        $where_conditions[] = "p.term = ?";
        $where_conditions[] = "(p.academic_year = ? OR p.academic_year IS NULL)";
        $params[] = $term;
        $params[] = $academic_year;
        $param_types .= "ss";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $sql = "
    SELECT 
        p.id,
        p.amount,
        p.payment_date,
        p.receipt_no,
        p.description,
        p.term
    FROM payments p
    $where_clause
    ORDER BY p.payment_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    $stmt->close();
    return $payments;
}

/**
 * Get arrears amount for a specific term (unpaid portion only)
 */
function getStudentTermArrears($conn, $student_id, $term, $academic_year = null) {
    if ($academic_year === null) {
        $academic_year = getAcademicYear($conn);
    }
    $sql = "
        SELECT COALESCE(SUM(sf.amount - sf.amount_paid), 0) AS arrears
        FROM student_fees sf
        WHERE sf.student_id = ? AND sf.term = ?
          AND (sf.academic_year = ? OR sf.academic_year IS NULL)
          AND sf.status != 'cancelled'
          AND (sf.amount - sf.amount_paid) > 0
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $student_id, $term, $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return floatval($row['arrears'] ?? 0);
}

/**
 * Snapshot arrears at end of a given term/year based only on payments recorded in that same term/year.
 * This prevents later-term payments (when allocation is global) from altering the carry-forward amount.
 */
function getTermArrearsSnapshot($conn, $student_id, $term, $academic_year) {
    // Total fees assigned in the term/year
    $fees_sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM student_fees WHERE student_id = ? AND term = ? AND (academic_year = ? OR academic_year IS NULL) AND status != 'cancelled'";
    $fees_stmt = $conn->prepare($fees_sql);
    $fees_stmt->bind_param('iss', $student_id, $term, $academic_year);
    $fees_stmt->execute();
    $fees_res = $fees_stmt->get_result();
    $fees_total = floatval(($fees_res->fetch_assoc()['total'] ?? 0));
    $fees_stmt->close();

        // Payments recorded in the same term/year (regardless of prior allocation)
        $paid_sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE student_id = ? AND term = ? AND (academic_year = ? OR academic_year IS NULL)";
        $paid_stmt = $conn->prepare($paid_sql);
        $paid_stmt->bind_param('iss', $student_id, $term, $academic_year);
        $paid_stmt->execute();
        $paid_res = $paid_stmt->get_result();
        $paid_total = floatval(($paid_res->fetch_assoc()['total'] ?? 0));
        $paid_stmt->close();

    return max(0, $fees_total - $paid_total);
}

/**
 * Compute outstanding from all terms/years outside the selected scope
 */
function getArrearsFromPreviousTerm($conn, $student_id, $current_term, $academic_year) {
    [$prev_term, $prev_year] = getPreviousTermYear($current_term, $academic_year);
    // Use snapshot based on payments within the previous term/year only
    return getTermArrearsSnapshot($conn, $student_id, $prev_term, $prev_year);
}

/**
 * Ensure a global fee template exists for arrears carry forward
 */
function getArrearsFeeId($conn) {
    $newName = 'Outstanding Balance';
    $oldName = 'Arrears Carry Forward';

    // Prefer the new name
    $stmt = $conn->prepare("SELECT id FROM fees WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $newName);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return intval($row['id']);
    }
    $stmt->close();

    // If the old name exists, migrate it to the new name
    $stmt2 = $conn->prepare("SELECT id FROM fees WHERE name = ? LIMIT 1");
    $stmt2->bind_param('s', $oldName);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($row2 = $res2->fetch_assoc()) {
        $fee_id = intval($row2['id']);
        $stmt2->close();
        $upd = $conn->prepare("UPDATE fees SET name = ?, description = 'Auto-created for outstanding balance carry forward' WHERE id = ?");
        $upd->bind_param('si', $newName, $fee_id);
        $upd->execute();
        $upd->close();
        return $fee_id;
    }
    $stmt2->close();

    // Otherwise, create the fee with the new name
    $ins = $conn->prepare("INSERT INTO fees (name, amount, fee_type, description) VALUES (?, 0, 'fixed', 'Auto-created for outstanding balance carry forward')");
    $ins->bind_param('s', $newName);
    if ($ins->execute()) {
        $fee_id = $conn->insert_id;
        $ins->close();
        return intval($fee_id);
    }
    $ins->close();
    return null;
}

/**
 * Create or update a student fee assignment in the current term/year to carry forward arrears
 */
function ensureArrearsAssignment($conn, $student_id, $current_term, $academic_year) {
    $arrears = getArrearsFromPreviousTerm($conn, $student_id, $current_term, $academic_year);
    $fee_id = getArrearsFeeId($conn);
    if (!$fee_id) return false;

    // Check existing assignment
    $check = $conn->prepare("SELECT id FROM student_fees WHERE student_id = ? AND fee_id = ? AND term = ? AND (academic_year = ? OR academic_year IS NULL) AND status != 'cancelled' LIMIT 1");
    $check->bind_param('iiss', $student_id, $fee_id, $current_term, $academic_year);
    $check->execute();
    $res = $check->get_result();
    $existing = $res->fetch_assoc();
    $check->close();

    if ($arrears <= 0) {
        // Remove any lingering arrears assignment if no arrears
        if ($existing) {
            $del = $conn->prepare("DELETE FROM student_fees WHERE id = ?");
            $del->bind_param('i', $existing['id']);
            $del->execute();
            $del->close();
        }
        return true;
    }

    if ($existing) {
        $upd = $conn->prepare("UPDATE student_fees SET amount = ?, status = 'pending', notes = 'Auto-assigned outstanding balance carry forward', due_date = CURDATE(), assigned_date = NOW() WHERE id = ?");
        $upd->bind_param('di', $arrears, $existing['id']);
        $ok = $upd->execute();
        $upd->close();
        return $ok;
    } else {
        $ins = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, due_date, amount, term, academic_year, notes, assigned_date, status) VALUES (?, ?, CURDATE(), ?, ?, ?, 'Auto-assigned outstanding balance carry forward', NOW(), 'pending')");
        $ins->bind_param('iidss', $student_id, $fee_id, $arrears, $current_term, $academic_year);
        $ok = $ins->execute();
        $ins->close();
        return $ok;
    }
}

/**
 * Fetch all assigned fees for a student in the selected term/year (pending or paid)
 */
function getStudentTermFees($conn, $student_id, $term, $academic_year = null) {
    if ($academic_year === null) {
        $academic_year = getAcademicYear($conn);
    }
    $sql = "
        SELECT 
            sf.id,
            f.name AS fee_name,
            sf.amount,
            sf.amount_paid,
            sf.due_date,
            sf.term,
            sf.assigned_date,
            sf.status,
            sf.notes
        FROM student_fees sf
        JOIN fees f ON sf.fee_id = f.id
        WHERE sf.student_id = ?
          AND sf.term = ?
          AND (sf.academic_year = ? OR sf.academic_year IS NULL)
          AND sf.status != 'cancelled'
        ORDER BY sf.due_date ASC, sf.assigned_date ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $student_id, $term, $academic_year);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}
?>