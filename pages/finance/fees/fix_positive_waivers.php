<?php
/**
 * ONE-TIME FIX: CORRECT POSITIVE WAIVERS
 * Run this script once from your browser to fix the mathematical bug.
 */
session_start();
require_once '../../../includes/db_connect.php';

// Only admins can run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Please login as admin.");
}

echo "<h3>Running Waiver Correction...</h3>";

$res = $conn->query("
    SELECT sf.id, f.name, sf.amount, sf.student_id
    FROM student_fees sf
    JOIN fees f ON sf.fee_id = f.id
    WHERE (f.name LIKE '%Waiver%' OR f.name LIKE '%Scholarship%') AND sf.amount > 0
");

$fixed_count = 0;
while ($r = $res->fetch_assoc()) {
    $id = $r['id'];
    $amount = $r['amount'];
    
    // Convert to negative deduction
    $conn->query("UPDATE student_fees SET amount = -amount WHERE id = $id");
    echo "<p>✅ Fixed Waiver ID $id for Student ID {$r['student_id']}: Changed +$amount to -$amount</p>";
    $fixed_count++;
}

echo "<h4>Done! $fixed_count waivers were successfully converted into deductions.</h4>";
echo "<a href='../reports/student_balance_details.php?id=128'>Go back to Glenda's Account</a>";
