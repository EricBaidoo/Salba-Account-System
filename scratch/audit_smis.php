<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_smis_audit');
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$sqlFile = 'c:/xampp/htdocs/ACCOUNTING/sql/u420775839_smis.sql';
$queries = file_get_contents($sqlFile);

if ($conn->multi_query($queries)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->error) {
    echo "Error: " . $conn->error . "\n";
} else {
    echo "Import successful.\n";
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

$rev = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_date BETWEEN '2026-01-01' AND '2026-04-30'")->fetch_assoc()['total'];
$exp = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN '2026-01-01' AND '2026-04-30'")->fetch_assoc()['total'];

echo "--- SMIS SQL AUDIT ---\n";
echo "Jan-Apr 2026 Revenue: GHS " . number_format($rev, 2) . "\n";
echo "Jan-Apr 2026 Expenses: GHS " . number_format($exp, 2) . "\n";

$sem_dist = $conn->query("SELECT semester, SUM(amount) as total FROM payments WHERE payment_date BETWEEN '2026-01-01' AND '2026-04-30' GROUP BY semester");
echo "Labels in Jan-Apr period:\n";
while($row = $sem_dist->fetch_assoc()) echo " - [" . ($row['semester'] ?: 'NULL') . "]: GHS " . number_format($row['total'], 2) . "\n";
?>
