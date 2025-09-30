<?php
include 'db_connect.php';

function getStudentBalance($conn, $student_id) {
    $sql = "
    SELECT 
        s.id as student_id,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.class,
        s.status as student_status,
        COALESCE(SUM(sf.amount), 0) as total_fees,
        (
            SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = s.id
        ) as total_payments,
        COUNT(CASE WHEN sf.status = 'pending' THEN 1 END) as pending_assignments,
        COUNT(CASE WHEN sf.status = 'paid' THEN 1 END) as paid_assignments
    FROM students s
    LEFT JOIN student_fees sf ON s.id = sf.student_id
    WHERE s.id = ?
    GROUP BY s.id, s.first_name, s.last_name, s.class, s.status
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $balance = $result->fetch_assoc();
    $stmt->close();
    
    if ($balance) {
        $balance['outstanding_fees'] = max(0, $balance['total_fees'] - $balance['total_payments']);
        $balance['net_balance'] = $balance['outstanding_fees'];
    }
    
    return $balance;
}

function getAllStudentBalances($conn, $class_filter = null, $status_filter = 'active') {
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    if ($status_filter && $status_filter !== 'all') {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }
    
    if ($class_filter && $class_filter !== 'all') {
        $where_conditions[] = "s.class = ?";
        $params[] = $class_filter;
        $param_types .= "s";
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql = "
    SELECT 
        s.id as student_id,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.class,
        s.status as student_status,
        COALESCE(SUM(sf.amount), 0) as total_fees,
        (
            SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = s.id
        ) as total_payments,
        COUNT(CASE WHEN sf.status = 'pending' THEN 1 END) as pending_assignments,
        COUNT(CASE WHEN sf.status = 'paid' THEN 1 END) as paid_assignments
    FROM students s
    LEFT JOIN student_fees sf ON s.id = sf.student_id
    {$where_clause}
    GROUP BY s.id, s.first_name, s.last_name, s.class, s.status
    ORDER BY s.class, s.first_name, s.last_name
    ";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $balances = [];
    while ($row = $result->fetch_assoc()) {
        $row['outstanding_fees'] = max(0, $row['total_fees'] - $row['total_payments']);
        $row['net_balance'] = $row['outstanding_fees'];
        $balances[] = $row;
    }
    
    if (!empty($params)) {
        $stmt->close();
    }
    
    return $balances;
}

function getStudentOutstandingFees($conn, $student_id) {
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
    WHERE sf.student_id = ? AND sf.status = 'pending'
    ORDER BY sf.due_date ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fees = [];
    while ($row = $result->fetch_assoc()) {
        $fees[] = $row;
    }
    
    $stmt->close();
    return $fees;
}

function getStudentPaymentHistory($conn, $student_id) {
    $sql = "
    SELECT 
        p.id,
        p.amount,
        p.payment_date,
        p.receipt_no,
        p.description
    FROM payments p
    WHERE p.student_id = ?
    ORDER BY p.payment_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    $stmt->close();
    return $payments;
}
?>