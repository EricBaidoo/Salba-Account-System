<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';
include 'includes/system_settings.php';
include 'includes/student_balance_functions.php';

$acad_year = '2025/2026';
$test_terms = ['NULL', 'First Semester', 'Second Semester', 'First Term', 'Term 1'];

foreach ($test_terms as $t_label) {
    $term = ($t_label === 'NULL') ? null : $t_label;
    $balances = getAllStudentBalances($conn, null, 'active', $term, $acad_year);
    
    $assigned = 0;
    $outstanding = 0;
    foreach ($balances as $s) {
        $assigned += (float)($s['total_fees'] ?? 0);
        $outstanding += (float)($s['net_balance'] ?? 0);
    }
    
    printf("Term [%s]: Assigned=%s | Outstanding=%s\n", $t_label, number_format($assigned, 2), number_format($outstanding, 2));
}
?>
