<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

echo "GOAL: Find Received=172,340.00, Spent=138,702.98, Pending=21,628.50\n\n";

// Audit Expenses first since we know 138,702.98 matches something
echo "--- SEARCHING FOR EXPENSES: 138,702.98 ---\n";
$res = $conn->query("SELECT academic_year, semester, SUM(amount) as total FROM expenses GROUP BY academic_year, semester");
while($row = $res->fetch_assoc()) {
    $matched = (floatval($row['total']) == 138702.98) ? "MATCH!" : "";
    printf("%-12s | %-20s | %-12s %s\n", $row['academic_year']??'NULL', $row['semester']??'NULL', number_format($row['total'], 2), $matched);
}

echo "\n--- SEARCHING FOR PAYMENTS: 172,340.00 ---\n";
// Try different filters: semester, academic_year, payment_type
$res = $conn->query("SELECT academic_year, semester, payment_type, SUM(amount) as total FROM payments GROUP BY academic_year, semester, payment_type");
while($row = $res->fetch_assoc()) {
    $matched = (floatval($row['total']) == 172340.00) ? "MATCH!" : "";
    printf("%-12s | %-20s | %-10s | %-12s %s\n", $row['academic_year']??'NULL', $row['semester']??'NULL', $row['payment_type'], number_format($row['total'], 2), $matched);
}

// Try combined student + general per term
echo "\n--- SEARCHING FOR PAYMENTS (Student+General) per Term ---\n";
$res = $conn->query("SELECT academic_year, semester, SUM(amount) as total FROM payments GROUP BY academic_year, semester");
while($row = $res->fetch_assoc()) {
    $matched = (floatval($row['total']) == 172340.00) ? "MATCH!" : "";
    printf("%-12s | %-20s | %-12s %s\n", $row['academic_year']??'NULL', $row['semester']??'NULL', number_format($row['total'], 2), $matched);
}

// Check if any specific student has 172k? (Unlikely)
// Check if any FEETYPE has 172k?

echo "\n--- SEARCHING FOR PENDING: 21,628.50 ---\n";
// Pending = Fees - Paid for a term
$terms = $conn->query("SELECT DISTINCT academic_year, semester FROM student_fees");
while($t = $terms->fetch_assoc()) {
    $y = $t['academic_year'];
    $s = $t['semester'];
    $s_clause = ($s === null) ? "IS NULL" : "= '$s'";
    $y_clause = ($y === null) ? "IS NULL" : "= '$y'";
    
    $fees = $conn->query("SELECT SUM(amount) as total FROM student_fees WHERE academic_year $y_clause AND semester $s_clause AND status != 'cancelled'")->fetch_assoc()['total'];
    $paid = $conn->query("SELECT SUM(amount) as total FROM payments WHERE academic_year $y_clause AND semester $s_clause")->fetch_assoc()['total'];
    $pending = floatval($fees) - floatval($paid);
    
    $matched = (number_format($pending, 2, '.', '') == "21628.50") ? "MATCH!" : "";
    printf("%-12s | %-20s | Fees: %-10s | Paid: %-10s | Pend: %-12s %s\n", $y??'NULL', $s??'NULL', number_format($fees, 0), number_format($paid, 0), number_format($pending, 2), $matched);
}

?>
