<?php
$conn = new mysqli('localhost', 'root', 'root');
$conn->query("CREATE DATABASE IF NOT EXISTS Salba_utf8_audit");
$conn->select_db("Salba_utf8_audit");
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$sqlFile = 'c:/xampp/htdocs/ACCOUNTING/sql/production_ready_utf8.sql';
$queries = file_get_contents($sqlFile);

if ($conn->multi_query($queries)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

echo "--- PRODUCTION READY UTF8 AUDIT ---\n";

// Check columns in payments
$res = $conn->query("DESCRIBE payments");
$cols = [];
while($row = $res->fetch_assoc()) $cols[] = $row['Field'];
echo "Payments columns: " . implode(', ', $cols) . "\n";

// Check totals for Jan-Apr 2026
$date_col = in_array('payment_date', $cols) ? 'payment_date' : 'date';
$rev = $conn->query("SELECT SUM(amount) as total FROM payments WHERE $date_col BETWEEN '2026-01-01' AND '2026-04-30'")->fetch_assoc()['total'];
echo "Jan-Apr 2026 Revenue: GHS " . number_format($rev, 2) . "\n";

// Check Terminology
$sem_col = in_array('semester', $cols) ? 'semester' : (in_array('term', $cols) ? 'term' : null);
if ($sem_col) {
    echo "Labels ($sem_col):\n";
    $res = $conn->query("SELECT `$sem_col`, SUM(amount) as total FROM payments WHERE $date_col BETWEEN '2026-01-01' AND '2026-04-30' GROUP BY `$sem_col` LIMIT 5");
    while($row = $res->fetch_assoc()) echo " - [" . ($row[$sem_col] ?: 'NULL') . "]: GHS " . number_format($row['total'], 2) . "\n";
}

?>
