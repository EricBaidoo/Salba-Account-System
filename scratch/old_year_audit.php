<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

$acad_year = '2024/2025';
echo "--- AUDIT FOR ACADEMIC YEAR: $acad_year ---\n";

$res = $conn->query("SELECT semester, SUM(amount) as total FROM payments WHERE academic_year = '$acad_year' GROUP BY semester");
while($row = $res->fetch_assoc()) {
    printf("Payments [%s]: %s\n", $row['semester']??'NULL', number_format($row['total'], 2));
}

$res = $conn->query("SELECT semester, SUM(amount) as total FROM student_fees WHERE academic_year = '$acad_year' AND status != 'cancelled' GROUP BY semester");
while($row = $res->fetch_assoc()) {
    $sem = $row['semester'];
    $s_val = ($sem === null) ? "IS NULL" : "= '$sem'";
    $paid = $conn->query("SELECT SUM(amount) as total FROM payments WHERE academic_year = '$acad_year' AND semester $s_val")->fetch_assoc()['total'];
    $pending = floatval($row['total']) - floatval($paid);
    printf("Fees [%s]: %s | Paid: %s | Pend: %s\n", $sem??'NULL', number_format($row['total'], 2), number_format($paid, 2), number_format($pending, 2));
}

?>
