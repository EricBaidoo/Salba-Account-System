<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

echo "--- EXPENSES 2025/2026 GROUPED BY SEMESTER ---\n";
$res = $conn->query("SELECT semester, SUM(amount) as total, COUNT(*) as cnt FROM expenses WHERE academic_year = '2025/2026' GROUP BY semester");
while($row = $res->fetch_assoc()) {
    printf("[%s]: %s (%d items)\n", $row['semester']??'NULL', number_format($row['total'], 2), $row['cnt']);
}

echo "\n--- PAYMENTS 2025/2026 GROUPED BY SEMESTER ---\n";
$res = $conn->query("SELECT semester, SUM(amount) as total, COUNT(*) as cnt FROM payments WHERE academic_year = '2025/2026' GROUP BY semester");
while($row = $res->fetch_assoc()) {
    printf("[%s]: %s (%d items)\n", $row['semester']??'NULL', number_format($row['total'], 2), $row['cnt']);
}

echo "\n--- BY DATE RANGES (Jan 2026 onwards) ---\n";
$res = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE academic_year = '2025/2026' AND expense_date >= '2026-01-01'");
echo "Expenses since Jan 2026: " . number_format($res->fetch_assoc()['total'], 2) . "\n";

$res = $conn->query("SELECT SUM(amount) as total FROM payments WHERE academic_year = '2025/2026' AND payment_date >= '2026-01-01'");
echo "Payments since Jan 2026: " . number_format($res->fetch_assoc()['total'], 2) . "\n";

?>
