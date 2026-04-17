<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';
include 'includes/semester_helpers.php';
include 'includes/system_settings.php';

$cs = getCurrentSemester($conn);
$ay = getAcademicYear($conn);

echo "CONTEXT: $cs | $ay\n";

$fees = $conn->query("SELECT SUM(amount) as s FROM student_fees WHERE semester = '$cs' AND academic_year = '$ay' AND status != 'cancelled'")->fetch_assoc()['s'];
$pay = $conn->query("SELECT SUM(amount) as s FROM payments WHERE semester = '$cs' AND academic_year = '$ay'")->fetch_assoc()['s'];
$exp = $conn->query("SELECT SUM(amount) as s FROM expenses WHERE semester = '$cs' AND academic_year = '$ay'")->fetch_assoc()['s'];

echo "Fees: " . number_format($fees, 2) . "\n";
echo "Paid: " . number_format($pay, 2) . "\n";
echo "Spent: " . number_format($exp, 2) . "\n";
?>
