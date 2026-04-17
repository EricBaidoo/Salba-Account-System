<?php
$c = new mysqli('localhost', 'root', 'root', 'Salba_acc');

function getRange($c, $table, $col, $start, $end) {
    $sql = "SELECT SUM(amount) as total, COUNT(*) as cnt FROM $table WHERE $col >= '$start' AND $col <= '$end'";
    return $c->query($sql)->fetch_assoc();
}

echo "--- FINANCIAL AUDIT BY INSTITUTIONAL CALENDAR ---\n";

// Second Semester: Jan - Apr 2026
$p2 = getRange($c, 'payments', 'payment_date', '2026-01-01', '2026-04-30');
$e2 = getRange($c, 'expenses', 'expense_date', '2026-01-01', '2026-04-30');
echo "\n[JAN-APR 2026] - TARGET: Second Semester\n";
echo "Payments: GHS " . number_format($p2['total'] ?? 0, 2) . " (" . $p2['cnt'] . " records)\n";
echo "Expenses: GHS " . number_format($e2['total'] ?? 0, 2) . " (" . $e2['cnt'] . " records)\n";

// First Semester: Sept - Dec 2025
$p1 = getRange($c, 'payments', 'payment_date', '2025-09-01', '2025-12-31');
$e1 = getRange($c, 'expenses', 'expense_date', '2025-09-01', '2025-12-31');
echo "\n[SEPT-DEC 2025] - TARGET: First Semester\n";
echo "Payments: GHS " . number_format($p1['total'] ?? 0, 2) . " (" . $p1['cnt'] . " records)\n";
echo "Expenses: GHS " . number_format($e1['total'] ?? 0, 2) . " (" . $e1['cnt'] . " records)\n";

echo "\n--- SUSPICIOUS PAYMENTS IN JAN 2026 (Likely First Semester Arrears?) ---\n";
// Total discrepancy is 33,515
// Looking for payments in Jan 2026 that might together equal 33,515 or contain 'arrears'
$res = $c->query("SELECT id, amount, payment_date, description FROM payments WHERE payment_date BETWEEN '2026-01-01' AND '2026-01-31' ORDER BY amount DESC LIMIT 20");
while($row = $res->fetch_assoc()) {
    echo "#" . $row['id'] . " | GHS " . number_format($row['amount'], 2) . " | " . $row['payment_date'] . " | " . $row['description'] . "\n";
}

$res = $c->query("SELECT SUM(amount) as total FROM payments WHERE payment_date BETWEEN '2026-01-01' AND '2026-04-30' AND (description LIKE '%arrear%' OR description LIKE '%first%')");
echo "\nPayments in Jan-Apr with 'Arrears' or 'First' in description: GHS " . number_format($res->fetch_assoc()['total'] ?? 0, 2) . "\n";
?>
