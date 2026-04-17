<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

echo "--- EXHAUSTIVE PAYMENTS AUDIT ---\n";
// Sometimes the column name is different?
$res = $conn->query("SELECT academic_year, semester, COUNT(*) as c, SUM(amount) as s FROM payments GROUP BY academic_year, semester");
while($row = $res->fetch_assoc()) {
    printf("%s | %s | %d items | SUM=%s\n", $row['academic_year']??'NULL', $row['semester']??'NULL', $row['c'], number_format($row['s'], 2));
}

echo "\n--- BY PAYMENT DATE (Check if 172,340 is a specific period) ---\n";
// Group by year-month
$res = $conn->query("SELECT LEFT(payment_date, 7) as ym, SUM(amount) as s FROM payments GROUP BY ym");
while($row = $res->fetch_assoc()) {
    printf("%s: %s\n", $row['ym'], number_format($row['s'], 2));
}

echo "\n--- BY STUDENT STATUS (Check if 172,340 is only active students) ---\n";
$res = $conn->query("SELECT s.status, SUM(p.amount) as s FROM payments p LEFT JOIN students s ON p.student_id = s.id WHERE p.academic_year = '2025/2026' GROUP BY s.status");
while($row = $res->fetch_assoc()) {
    printf("%s: %s\n", $row['status']??'NULL', number_format($row['s'], 2));
}
?>
