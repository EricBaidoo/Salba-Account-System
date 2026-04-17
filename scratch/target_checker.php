<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

$targets = [
    'received' => 172340.00,
    'pending' => 21628.50,
    'spent' => 138702.98
];

echo "Searching for target Received: 172,340.00\n";

// Combined query: student payments where s.status = 'active' OR general
// Note: Schema check said no 'term' column. Let's see if 'semester' column has these matches.
$acad_year = '2025/2026';

$semesters = $conn->query("SELECT DISTINCT semester FROM payments");
while($s_row = $semesters->fetch_assoc()) {
    $sem = $s_row['semester'];
    $s_val = ($sem === null) ? "IS NULL" : "= '$sem'";
    
    // Test the specific query from old dashboard
    $sql = "SELECT SUM(p.amount) as total 
            FROM payments p 
            LEFT JOIN students s ON p.student_id = s.id 
            WHERE (p.semester $s_val) 
              AND p.academic_year = '$acad_year' 
              AND (s.status = 'active' OR p.payment_type = 'general')";
    $res = $conn->query($sql);
    $total = $res->fetch_assoc()['total'];
    printf("Sem [%s] (Active/General): %s\n", $sem??'NULL', number_format($total, 2));
    
    // Test only student
    $sql = "SELECT SUM(p.amount) as total 
            FROM payments p 
            JOIN students s ON p.student_id = s.id 
            WHERE (p.semester $s_val) 
              AND p.academic_year = '$acad_year' 
              AND s.status = 'active'";
    $res = $conn->query($sql);
    $total = $res->fetch_assoc()['total'];
    printf("Sem [%s] (Only Student Active): %s\n", $sem??'NULL', number_format($total, 2));
}

echo "\nChecking if 172,340 matches SUM(amount_paid) in student_fees:\n";
$semesters = $conn->query("SELECT DISTINCT semester FROM student_fees");
while($s_row = $semesters->fetch_assoc()) {
    $sem = $s_row['semester'];
    $s_val = ($sem === null) ? "IS NULL" : "= '$sem'";
    $sql = "SELECT SUM(amount_paid) as total FROM student_fees WHERE semester $s_val AND academic_year = '$acad_year'";
    $res = $conn->query($sql);
    $total = $res->fetch_assoc()['total'];
    printf("Sem [%s] (student_fees.amount_paid): %s\n", $sem??'NULL', number_format($total, 2));
}

?>
