<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

$start = '2026-01-01';
$end = '2026-04-30';

echo "--- SEMESTER DATE RANGE AUDIT (Jan-Apr 2026) ---\n";

$res = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN '$start' AND '$end'");
$spent = $res->fetch_assoc()['total'];
echo "Spent (Expenses): " . number_format($spent, 2) . "\n";

$res = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_date BETWEEN '$start' AND '$end'");
$received = $res->fetch_assoc()['total'];
echo "Received (Payments): " . number_format($received, 2) . "\n";

echo "\n--- BY SEMESTER LABEL (Current DB state) ---\n";
$res = $conn->query("SELECT semester, SUM(amount) as s FROM expenses WHERE expense_date BETWEEN '$start' AND '$end' GROUP BY semester");
while($row = $res->fetch_assoc()) echo "Exp Label [" . ($row['semester']??'NULL') . "]: " . number_format($row['s'], 2) . "\n";

$res = $conn->query("SELECT semester, SUM(amount) as s FROM payments WHERE payment_date BETWEEN '$start' AND '$end' GROUP BY semester");
while($row = $res->fetch_assoc()) echo "Pay Label [" . ($row['semester']??'NULL') . "]: " . number_format($row['s'], 2) . "\n";

?>
