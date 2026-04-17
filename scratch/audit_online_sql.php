<?php
$c = new mysqli('localhost', 'root', 'root', 'Salba_online_audit');

function getRange($c, $table, $col, $start, $end) {
    $sql = "SELECT SUM(amount) as total, COUNT(*) as cnt FROM `$table` WHERE `$col` >= '$start' AND `$col` <= '$end'";
    $res = $c->query($sql);
    return $res ? $res->fetch_assoc() : ['total' => 0, 'cnt' => 0];
}

echo "--- ONLINE DATA AUDIT (PROD SQL SNAPSHOT) ---\n";

// Check if tables exist (old system might use term instead of semester)
$tables = ['payments', 'expenses'];
foreach ($tables as $t) {
    echo "\nAnalyzing Table: $t\n";
    $desc = $c->query("DESCRIBE `$t`")->fetch_all(MYSQLI_ASSOC);
    $cols = array_column($desc, 'Field');
    
    $date_col = ($t == 'payments' ? 'payment_date' : 'expense_date');
    if ($t == 'expenses' && !in_array('expense_date', $cols)) $date_col = 'date'; // Fallback
    
    $sem_col = in_array('semester', $cols) ? 'semester' : (in_array('term', $cols) ? 'term' : null);
    
    // Jan-Apr 2026 (Second Semester)
    $r2 = getRange($c, $t, $date_col, '2026-01-01', '2026-04-30');
    echo "  [JAN-APR 2026]: GHS " . number_format($r2['total'] ?? 0, 2) . " (" . $r2['cnt'] . " records)\n";

    // Sept-Dec 2025 (First Semester)
    $r1 = getRange($c, $t, $date_col, '2025-09-01', '2025-12-31');
    echo "  [SEPT-DEC 2025]: GHS " . number_format($r1['total'] ?? 0, 2) . " (" . $r1['cnt'] . " records)\n";
    
    if ($sem_col) {
        echo "  Grouped by '$sem_col' label (Top 5):\n";
        $res = $c->query("SELECT `$sem_col`, SUM(amount) as total FROM `$t` GROUP BY `$sem_col` ORDER BY total DESC LIMIT 5");
        while($row = $res->fetch_assoc()) echo "    " . ($row[$sem_col] ?: 'NULL') . ": GHS " . number_format($row['total'], 2) . "\n";
    }
}
?>
