<?php
$c = new mysqli('localhost', 'root', 'root', 'Salba_acc');

echo "--- ARREARS PAYMENTS AUDIT (JAN-APR 2026) ---\n";
// Find payments in Second Semester date range that are allocated to First Semester fees
$sql = "SELECT SUM(pa.amount) as total 
        FROM payment_allocations pa 
        JOIN student_fees sf ON pa.student_fee_id = sf.id 
        JOIN payments p ON pa.payment_id = p.id 
        WHERE p.payment_date BETWEEN '2026-01-01' AND '2026-04-30' 
        AND sf.semester = 'First Semester'";
$res = $c->query($sql);
echo "Payments received in Jan-Apr but allocated to First Semester: GHS " . number_format($res->fetch_assoc()['total'] ?? 0, 2) . "\n";

// Check if the discrepancy (33,515.00) matches this?
?>
