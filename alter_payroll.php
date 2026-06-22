<?php
require 'includes/db_connect.php';

$sql1 = "ALTER TABLE staff_salary_structures ADD COLUMN deduction_reason VARCHAR(255) DEFAULT NULL AFTER deductions;";
$sql2 = "ALTER TABLE payroll_records ADD COLUMN deduction_reason VARCHAR(255) DEFAULT NULL AFTER deductions;";

$conn->query($sql1);
$conn->query($sql2);

echo "Alter complete.";
